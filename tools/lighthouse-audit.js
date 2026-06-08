import fs from 'fs';
import path from 'path';
import { chromium } from 'playwright';
import lighthouse from 'lighthouse';
import { launch } from 'chrome-launcher';

const args = process.argv.slice(2);
const options = {};
const positional = [];

for (const arg of args) {
    if (arg.startsWith('--')) {
        const [key, value] = arg.split(/=(.*)/s);
        options[key] = value === undefined ? true : value;
    } else {
        positional.push(arg);
    }
}

const url = options['--url'] || positional[0];
if (!url) {
    console.error('Usage: node tools/lighthouse-audit.js <url> [--stdout] [--output=path] [--url=<url>]');
    process.exit(1);
}

async function run() {
    const chromePath = chromium.executablePath();
    if (!chromePath) {
        throw new Error('Playwright chromium executable not found. Run `npx playwright install --with-deps` first.');
    }

    const chrome = await launch({
        chromePath,
        chromeFlags: ['--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage'],
    });

    try {
        const flags = {
            port: chrome.port,
            output: 'json',
            logLevel: 'info',
            onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
        };

        const { lhr } = await lighthouse(url, flags);
        const outputJson = JSON.stringify(lhr, null, 2);

        if (options['--output']) {
            const outPath = path.resolve(process.cwd(), options['--output']);
            fs.writeFileSync(outPath, outputJson, 'utf8');
            console.log(`Lighthouse audit saved to ${outPath}`);
        }

        if (options['--stdout']) {
            process.stdout.write(outputJson);
        }

        if (!options['--stdout'] && !options['--output']) {
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const filename = `lighthouse-${new URL(url).hostname}-${timestamp}.json`;
            const outPath = path.resolve(process.cwd(), filename);
            fs.writeFileSync(outPath, outputJson, 'utf8');
            console.log(`Lighthouse audit saved to ${outPath}`);
        }
    } catch (error) {
        console.error('Lighthouse audit failed:', error);
        process.exit(1);
    } finally {
        if (chrome) {
            try {
                await chrome.kill();
            } catch (cleanupError) {
                console.warn('Chrome cleanup failed, ignoring:', cleanupError.message || cleanupError);
            }
        }
    }
}

run().catch((error) => {
    console.error('Unexpected error:', error);
    process.exit(1);
});