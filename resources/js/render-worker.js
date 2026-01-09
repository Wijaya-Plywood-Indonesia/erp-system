import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url'; // Diperlukan untuk __dirname
import { execSync } from 'child_process';
import sharp from 'sharp';

// --- TRIK UNTUK MEMBUAT __dirname DI ES MODULE ---
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Terima argumen dari PHP
const forceUpdate = process.argv[2] === '--force';

// Path (Mundur 2 folder dari resources/js ke root, lalu masuk relations)
// Sesuaikan '../..' ini tergantung seberapa dalam folder script Anda
const inputDir = path.join(__dirname, '../../relations/input');
const outputDir = path.join(__dirname, '../../relations/output');

if (!fs.existsSync(outputDir)) fs.mkdirSync(outputDir, { recursive: true });

// Cari file .dbml
const files = fs.readdirSync(inputDir).filter(file => file.endsWith('.dbml'));

console.log(`[NodeJS] Memproses ${files.length} file...`);

(async () => {
    for (const file of files) {
        const name = path.parse(file).name;
        const inputPath = path.join(inputDir, file);
        const svgPath = path.join(outputDir, `${name}.temp.svg`);
        const pngPath = path.join(outputDir, `${name}.png`);

        // 1. Cek apakah PNG sudah ada
        if (fs.existsSync(pngPath) && !forceUpdate) {
            console.log(`SKIP   : ${name}.png (Sudah ada)`);
            continue;
        }

        try {
            // 2. Render DBML ke SVG
            execSync(`npx dbml-renderer -i "${inputPath}" -o "${svgPath}"`);

            // 3. Konversi SVG ke PNG
            await sharp(svgPath)
                .png({ quality: 100 })
                .toFile(pngPath);

            // 4. Hapus file SVG sementara
            if (fs.existsSync(svgPath)) fs.unlinkSync(svgPath);

            console.log(`RENDER : ${name}.png ... OK`);

        } catch (err) {
            console.error(`ERROR  : Gagal memproses ${name}.dbml`);
            // console.error(err);
        }
    }
})();   