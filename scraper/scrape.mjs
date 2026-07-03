// Scraper de página de produto — chamado pelo Laravel via processo:
//   node scrape.mjs "https://loja.com/produto"
// Imprime JSON {title, price, imageUrl, sourceUrl} no stdout (exit 0) ou
// {error} (exit 1). Usa Puppeteer + stealth para contornar anti-bot
// (ex.: parede de verificação do Mercado Livre para headless "puro").
import puppeteer from 'puppeteer-extra';
import Stealth from 'puppeteer-extra-plugin-stealth';

puppeteer.use(Stealth());

const url = process.argv[2];
if (!url || !/^https?:\/\//i.test(url)) {
  process.stdout.write(JSON.stringify({ error: 'URL inválida' }));
  process.exit(1);
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function absolutize(maybeUrl, base) {
  if (!maybeUrl) return null;
  try {
    return new URL(maybeUrl, base).toString();
  } catch {
    return null;
  }
}

let browser;
try {
  browser = await puppeteer.launch({
    headless: true,
    executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--lang=pt-BR'],
  });
  const page = await browser.newPage();
  await page.setExtraHTTPHeaders({ 'Accept-Language': 'pt-BR,pt;q=0.9,en;q=0.8' });
  await page.setViewport({ width: 1366, height: 900 });

  // networkidle2 dá tempo para SPAs (ML, etc.) carregarem e evita ler durante redirect.
  try {
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 45000 });
  } catch {
    // Se estourar o networkidle, segue e tenta ler o que já carregou.
  }
  // Espera ativa: muitos sites (ML) injetam JSON-LD/preço via JS após a hidratação.
  try {
    await page.waitForFunction(
      () =>
        document.querySelector('script[type="application/ld+json"]') ||
        document.querySelector('[itemprop="price"]') ||
        document.querySelector('.andes-money-amount__fraction') ||
        document.querySelector('meta[property="og:price:amount"]'),
      { timeout: 8000 },
    );
  } catch {
    // segue com o que houver
  }
  await sleep(600);

  const data = await page.evaluate(() => {
    const pick = (sel, attr) => {
      const el = document.querySelector(sel);
      return el ? (attr ? el.getAttribute(attr) : el.textContent) : null;
    };

    // 1) JSON-LD schema.org/Product
    const ld = { title: null, price: null, image: null };
    for (const node of document.querySelectorAll('script[type="application/ld+json"]')) {
      try {
        const json = JSON.parse(node.textContent);
        const arr = Array.isArray(json) ? json : (json['@graph'] || [json]);
        for (const item of arr) {
          if (!item || typeof item !== 'object') continue;
          const type = item['@type'];
          // Alguns sites (ex.: Mercado Livre) omitem @type "Product"; trata como
          // produto se houver nome + offers.
          const isProduct =
            type === 'Product' ||
            (Array.isArray(type) && type.includes('Product')) ||
            (!!item.name && !!item.offers);
          if (isProduct) {
            ld.title = ld.title || item.name || null;
            const img = Array.isArray(item.image) ? item.image[0] : item.image;
            ld.image = ld.image || (typeof img === 'object' ? img?.url : img) || null;
            const offers = Array.isArray(item.offers) ? item.offers[0] : item.offers;
            if (offers) {
              const p =
                offers.price ||
                offers.lowPrice ||
                (offers.priceSpecification && offers.priceSpecification.price);
              const cur =
                offers.priceCurrency ||
                (offers.priceSpecification && offers.priceSpecification.priceCurrency) ||
                '';
              if (p) ld.price = cur === 'BRL' ? `R$ ${p}` : `${cur} ${p}`.trim();
            }
          }
        }
      } catch {
        /* ignora JSON inválido */
      }
    }

    // 2) Open Graph / meta
    const ogTitle =
      pick('meta[property="og:title"]', 'content') ||
      pick('meta[name="twitter:title"]', 'content');
    const ogImage =
      pick('meta[property="og:image"]', 'content') ||
      pick('meta[name="twitter:image"]', 'content');
    const ogPrice =
      pick('meta[property="product:price:amount"]', 'content') ||
      pick('meta[property="og:price:amount"]', 'content');
    const ogCurrency = pick('meta[property="product:price:currency"]', 'content') || '';

    // 3) Seletores comuns de preço (Mercado Livre usa .andes-money-amount__fraction)
    const moneyEl = document.querySelector('.andes-money-amount__fraction');
    let widgetPrice = null;
    if (moneyEl) {
      const fraction = moneyEl.textContent?.trim();
      const cents = document.querySelector('.andes-money-amount__cents')?.textContent?.trim();
      if (fraction) widgetPrice = `R$ ${fraction}${cents ? ',' + cents : ''}`;
    }

    // 4) Heurística genérica no DOM
    const domPrice =
      pick('[itemprop="price"]', 'content') ||
      pick('[itemprop="price"]') ||
      pick('[class*="price"]');

    // Título: prioriza h1 da página de produto sobre o <title> genérico
    const h1 = document.querySelector('h1')?.textContent?.trim() || null;

    return {
      // Prioriza nome limpo (JSON-LD/h1) antes do og:title, que às vezes inclui o preço.
      title: ld.title || h1 || ogTitle || (document.title || '').trim() || null,
      price:
        ld.price ||
        (ogPrice ? `${ogCurrency} ${ogPrice}`.trim() : null) ||
        widgetPrice ||
        (domPrice ? domPrice.trim() : null),
      image: ld.image || ogImage || null,
      baseUrl: document.baseURI,
    };
  });

  process.stdout.write(
    JSON.stringify({
      title: data.title,
      price: data.price,
      imageUrl: absolutize(data.image, data.baseUrl || url),
      sourceUrl: url,
    }),
  );
} catch (err) {
  process.stdout.write(JSON.stringify({ error: err.message || 'Falha no scraping' }));
  process.exitCode = 1;
} finally {
  if (browser) await browser.close();
}
