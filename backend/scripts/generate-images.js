#!/usr/bin/env node
/**
 * ArenaBattle_0 Image Asset Generator
 *
 * Usage:
 *   node scripts/generate-images.js [options]
 *
 * Options:
 *   --provider=pollinations  (default) | openai | stability | placeholder
 *   --category=characters   (default) | backgrounds | ui | all
 *   --limit=N                Generate only first N entries (for testing)
 *   --dry-run                Print URLs/paths without downloading
 *   --placeholder            Generate SVG placeholders instead of calling API
 *
 * Environment:
 *   IMAGE_PROVIDER         Default provider (overridden by --provider)
 *   OPENAI_API_KEY         Required for openai
 *   STABILITY_API_KEY      Required for stability
 *   POLLINATIONS_PARAMS    Extra query params for pollinations (e.g. "nologo=true&negative_prompt=text")
 */

import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import crypto from 'node:crypto';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const ROOT = path.resolve(__dirname, '..');
const MANIFEST_PATH = path.join(__dirname, 'assets-manifest.json');

// ── CLI args ────────────────────────────────────────────────────────────────
const args = process.argv.slice(2).reduce((acc, arg) => {
    if (arg.startsWith('--')) {
        const [k, v = true] = arg.slice(2).split('=');
        acc[k] = v;
    }
    return acc;
}, {});

const providerName = args.provider || process.env.IMAGE_PROVIDER || 'pollinations';
const categoryFilter = args.category || 'all';
const limit = args.limit ? parseInt(args.limit, 10) : Infinity;
const dryRun = args['dry-run'] !== undefined;
const forcePlaceholder = args.placeholder !== undefined;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const hash = (str) => crypto.createHash('sha256').update(str).digest('hex');
const slug = (str) => str.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');

// ── Placeholder SVG generators ───────────────────────────────────────────────
function characterPlaceholderSvg(filename, label) {
    const colors = [
        ['#1e293b', '#3b82f6'],
        ['#1e293b', '#ef4444'],
        ['#1e293b', '#10b981'],
        ['#1e293b', '#f59e0b'],
        ['#1e293b', '#8b5cf6'],
        ['#1e293b', '#ec4899'],
    ];
    const idx = Math.abs(hash(filename).split('').reduce((a, b) => a + b.charCodeAt(0), 0)) % colors.length;
    const [bg, fg] = colors[idx];
    const initials = label
        .split(' ')
        .map((w) => w[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">
  <defs>
    <radialGradient id="g" cx="50%" cy="50%" r="70%">
      <stop offset="0%" stop-color="${fg}" stop-opacity="0.4"/>
      <stop offset="100%" stop-color="${bg}" stop-opacity="1"/>
    </radialGradient>
  </defs>
  <rect width="400" height="400" fill="${bg}"/>
  <circle cx="200" cy="200" r="140" fill="url(#g)"/>
  <text x="200" y="230" font-family="ui-sans-serif, system-ui, sans-serif" font-size="96" font-weight="bold" fill="white" text-anchor="middle">${initials}</text>
  <text x="200" y="360" font-family="ui-sans-serif, system-ui, sans-serif" font-size="20" fill="#94a3b8" text-anchor="middle">${label}</text>
</svg>`;
}

function backgroundPlaceholderSvg(filename, label) {
    return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720">
  <rect width="1280" height="720" fill="#0f172a"/>
  <text x="640" y="360" font-family="ui-sans-serif, system-ui, sans-serif" font-size="48" font-weight="bold" fill="#475569" text-anchor="middle" dominant-baseline="middle">${label}</text>
</svg>`;
}

function uiPlaceholderSvg(filename, label) {
    return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">
  <circle cx="64" cy="64" r="60" fill="#1e293b" stroke="#475569" stroke-width="4"/>
  <text x="64" y="74" font-family="ui-sans-serif, system-ui, sans-serif" font-size="48" font-weight="bold" fill="white" text-anchor="middle">${label.slice(0, 2).toUpperCase()}</text>
</svg>`;
}

// ── API callers ─────────────────────────────────────────────────────────────
async function callPollinations(prompt, outputPath, size, seed) {
    const params = new URLSearchParams({
        width: String(size.width),
        height: String(size.height),
        nologo: 'true',
        seed: String(seed),
    });
    if (process.env.POLLINATIONS_PARAMS) {
        const extra = new URLSearchParams(process.env.POLLINATIONS_PARAMS);
        for (const [k, v] of extra) {
            if (!params.has(k)) params.set(k, v);
        }
    }

    const encodedPrompt = encodeURIComponent(prompt);
    const url = `https://image.pollinations.ai/prompt/${encodedPrompt}?${params.toString()}`;

    if (dryRun) {
        console.log(`  [dry-run] would fetch: ${url}`);
        return { skipped: true, url };
    }

    const res = await fetch(url, { redirect: 'follow' });
    if (!res.ok) {
        throw new Error(`Pollinations HTTP ${res.status}: ${await res.text().catch(() => '')}`);
    }
    const buf = Buffer.from(await res.arrayBuffer());
    await fs.writeFile(outputPath, buf);
    return { ok: true, url, bytes: buf.length };
}

async function callOpenAI(prompt, outputPath, size) {
    const apiKey = process.env.OPENAI_API_KEY;
    if (!apiKey) throw new Error('OPENAI_API_KEY is required for provider=openai');

    const body = {
        model: process.env.OPENAI_MODEL || 'dall-e-3',
        prompt,
        n: 1,
        size: `${size.width}x${size.height}`,
        response_format: 'b64_json',
    };

    if (dryRun) {
        console.log(`  [dry-run] would call OpenAI: ${body.model} ${body.size}`);
        return { skipped: true };
    }

    const res = await fetch('https://api.openai.com/v1/images/generations', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${apiKey}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
    });

    if (!res.ok) {
        throw new Error(`OpenAI HTTP ${res.status}: ${await res.text().catch(() => '')}`);
    }

    const json = await res.json();
    const b64 = json.data?.[0]?.b64_json;
    if (!b64) throw new Error('OpenAI response missing b64_json');

    const buf = Buffer.from(b64, 'base64');
    await fs.writeFile(outputPath, buf);
    return { ok: true, bytes: buf.length };
}

async function callStability(prompt, outputPath, size) {
    const apiKey = process.env.STABILITY_API_KEY;
    if (!apiKey) throw new Error('STABILITY_API_KEY is required for provider=stability');

    const form = new URLSearchParams();
    form.append('prompt', prompt);
    form.append('output_format', 'png');
    form.append('width', String(size.width));
    form.append('height', String(size.height));

    if (dryRun) {
        console.log(`  [dry-run] would call Stability: ${size.width}x${size.height}`);
        return { skipped: true };
    }

    const res = await fetch('https://api.stability.ai/v2beta/stable-image/generate/sd3', {
        method: 'POST',
        headers: {
            'Authorization': `${apiKey}`,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: form.toString(),
    });

    if (!res.ok) {
        throw new Error(`Stability HTTP ${res.status}: ${await res.text().catch(() => '')}`);
    }

    const buf = Buffer.from(await res.arrayBuffer());
    await fs.writeFile(outputPath, buf);
    return { ok: true, bytes: buf.length };
}

async function generatePlaceholder(entry, outputPath, category) {
    let svg;
    const label = entry.label || entry.name || entry.subject || entry.icon_key || 'asset';
    if (category === 'characters') {
        svg = characterPlaceholderSvg(path.basename(outputPath), label);
    } else if (category === 'backgrounds') {
        svg = backgroundPlaceholderSvg(path.basename(outputPath), label);
    } else if (category === 'ui') {
        svg = uiPlaceholderSvg(path.basename(outputPath), label);
    } else {
        svg = uiPlaceholderSvg(path.basename(outputPath), label);
    }

    if (dryRun) {
        console.log(`  [dry-run] would write SVG placeholder: ${outputPath}`);
        return { skipped: true };
    }

    await fs.mkdir(path.dirname(outputPath), { recursive: true });
    await fs.writeFile(outputPath.replace(/\.png$/, '.svg'), svg);
    return { ok: true, placeholder: true };
}

async function generateOne(entry, categoryConfig, categoryName) {
    const promptTemplate = entry.prompt || categoryConfig.promptTemplate || '{subject}';
    const prompt = promptTemplate
        .replace('{style}', categoryConfig.stylePrefix || '')
        .replace('{subject}', entry.subject || '')
        .replace('{details}', entry.details || '')
        .replace('{description}', entry.description || '')
        .replace(/{stylePrefix}/g, categoryConfig.stylePrefix || '')
        .trim()
        .replace(/\s+/g, ' ');

    if (!prompt) {
        throw new Error('Prompt is empty');
    }

    const outputDir = path.join(ROOT, categoryConfig.outputDir);
    const size = entry.size || categoryConfig.size;

    // Build filename
    let filename = categoryConfig.naming
        .replace('{icon_key}', entry.icon_key || '')
        .replace('{gender}', entry.gender || 'unknown')
        .replace('{icon_index}', String(entry.icon_index ?? 0))
        .replace('{name}', entry.name || '');

    if (categoryConfig.format && !filename.endsWith(`.${categoryConfig.format}`)) {
        filename += `.${categoryConfig.format}`;
    }

    const outputPath = path.join(outputDir, filename);

    // Skip existing unless placeholder mode
    try {
        await fs.access(outputPath);
        if (!forcePlaceholder) {
            return { skipped: true, path: outputPath, reason: 'already exists' };
        }
    } catch {
        // file does not exist, continue
    }

    await fs.mkdir(outputDir, { recursive: true });

    const seed = Math.abs(parseInt(hash(filename).slice(0, 8), 16)) % 2147483647;
    entry.label = entry.label || entry.subject || entry.name || entry.icon_key || 'asset';

    if (forcePlaceholder || providerName === 'placeholder') {
        return await generatePlaceholder(entry, outputPath, categoryName);
    }

    switch (providerName) {
        case 'pollinations':
            return await callPollinations(prompt, outputPath, size, seed);
        case 'openai':
            return await callOpenAI(prompt, outputPath, size);
        case 'stability':
            return await callStability(prompt, outputPath, size);
        default:
            throw new Error(`Unknown provider: ${providerName}`);
    }
}

async function expandEntries(categoryConfig, categoryName) {
    const entries = [];
    for (const entry of categoryConfig.entries) {
        // For entries with indices array, expand to multiple files
        if (Array.isArray(entry.indices) && entry.indices.length > 0) {
            for (const idx of entry.indices) {
                entries.push({ ...entry, icon_index: idx });
            }
        } else {
            entries.push(entry);
        }
    }
    return entries.map((e) => ({ ...e, category: categoryName, size: e.size || categoryConfig.size }));
}

// ── Main ───────────────────────────────────────────────────────────────────
async function main() {
    console.log(`ArenaBattle Asset Generator`);
    console.log(`Provider: ${providerName}${forcePlaceholder ? ' (placeholder mode)' : ''}`);
    console.log(`Category: ${categoryFilter}`);
    console.log(`Dry run:  ${dryRun}`);
    console.log('');

    const manifest = JSON.parse(await fs.readFile(MANIFEST_PATH, 'utf8'));

    let allEntries = [];
    for (const [categoryName, categoryConfig] of Object.entries(manifest.categories)) {
        if (categoryFilter !== 'all' && categoryFilter !== categoryName) continue;
        const expanded = await expandEntries(categoryConfig, categoryName);
        allEntries = allEntries.concat(expanded);
    }

    if (Number.isFinite(limit)) {
        allEntries = allEntries.slice(0, limit);
    }

    console.log(`Total assets to generate: ${allEntries.length}`);
    console.log('');

    const results = { ok: 0, failed: 0, skipped: 0, placeholders: 0 };
    const failures = [];

    for (let i = 0; i < allEntries.length; i++) {
        const entry = allEntries[i];
        const categoryName = entry.category;
        const categoryConfig = manifest.categories[categoryName];

        // Rebuild filename for logging
        let filename = categoryConfig.naming
            .replace('{icon_key}', entry.icon_key || '')
            .replace('{gender}', entry.gender || 'unknown')
            .replace('{icon_index}', String(entry.icon_index ?? 0))
            .replace('{name}', entry.name || '');
        if (categoryConfig.format && !filename.endsWith(`.${categoryConfig.format}`)) {
            filename += `.${categoryConfig.format}`;
        }

        console.log(`[${i + 1}/${allEntries.length}] ${filename}`);

        try {
            const result = await generateOne(entry, categoryConfig, categoryName);
            if (result.skipped) {
                results.skipped++;
                console.log(`  skipped: ${result.reason || 'dry-run/placeholder'}`);
            } else if (result.ok) {
                if (result.placeholder) {
                    results.placeholders++;
                    console.log(`  placeholder OK`);
                } else {
                    results.ok++;
                    console.log(`  downloaded (${result.bytes ? (result.bytes / 1024).toFixed(1) + 'KB' : 'SVG'})`);
                }
            }
        } catch (err) {
            results.failed++;
            failures.push({ filename, error: err.message });
            console.error(`  ERROR: ${err.message}`);
        }

        // Be polite to free API
        if (providerName === 'pollinations' && !dryRun && !forcePlaceholder) {
            await sleep(500);
        }
    }

    console.log('');
    console.log('─────────────────────────────────');
    console.log(`Generated: ${results.ok}`);
    console.log(`Placeholders: ${results.placeholders}`);
    console.log(`Skipped:   ${results.skipped}`);
    console.log(`Failed:    ${results.failed}`);
    console.log('─────────────────────────────────');

    if (failures.length > 0) {
        console.log('');
        console.log('Failures:');
        for (const f of failures) {
            console.log(`  - ${f.filename}: ${f.error}`);
        }
        process.exit(1);
    }

    console.log('');
    console.log('All done. Run `npm run build` in the backend directory to incorporate assets into the production build.');
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
