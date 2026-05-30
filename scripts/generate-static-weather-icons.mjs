import { promises as fs } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const repoRoot = process.cwd();
const sourceDir = path.join(repoRoot, 'public', 'icons', 'weather');
const targetDir = path.join(repoRoot, 'public', 'icons', 'weather-static');

function stripSvgAnimations(svg) {
  return svg
    // SMIL animation elements (self-closing)
    .replace(/<animate(?:Transform|Motion|Color)?\b[^>]*\/>/g, '')
    .replace(/<set\b[^>]*\/>/g, '')
    // SMIL animation elements (with closing tag)
    .replace(/<animate(?:Transform|Motion|Color)?\b[^>]*>[\s\S]*?<\/animate(?:Transform|Motion|Color)?>/g, '')
    .replace(/<set\b[^>]*>[\s\S]*?<\/set>/g, '');
}

async function main() {
  await fs.mkdir(targetDir, { recursive: true });

  const entries = await fs.readdir(sourceDir, { withFileTypes: true });
  const svgFiles = entries
    .filter((entry) => entry.isFile() && entry.name.toLowerCase().endsWith('.svg'))
    .map((entry) => entry.name);

  let changedCount = 0;
  for (const fileName of svgFiles) {
    const sourcePath = path.join(sourceDir, fileName);
    const targetPath = path.join(targetDir, fileName);

    const original = await fs.readFile(sourcePath, 'utf8');
    const stripped = stripSvgAnimations(original);
    if (stripped !== original) {
      changedCount += 1;
    }
    await fs.writeFile(targetPath, stripped, 'utf8');
  }

  // eslint-disable-next-line no-console
  console.log(`Generated ${svgFiles.length} static icons in ${path.relative(repoRoot, targetDir)} (${changedCount} modified).`);
}

await main();

