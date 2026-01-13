import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';
import sharp from 'sharp';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Ambil argumen force
const forceUpdate = process.argv.includes('--force');

// Path mundur 2 tingkat dari resources/js ke root project
const inputDir = path.join(__dirname, '../../relations/input');
const outputDir = path.join(__dirname, '../../relations/output');

if (!fs.existsSync(outputDir)) fs.mkdirSync(outputDir, { recursive: true });

// 1. DEBUG: Cek apakah folder terbaca
try {
    const allFiles = fs.readdirSync(inputDir);
    // Filter case-insensitive (dbml, DBML, Dbml)
    const dbmlFiles = allFiles.filter(file => file.toLowerCase().endsWith('.dbml'));

    console.log(`[NodeJS] Folder Input: ${inputDir}`);
    console.log(`[NodeJS] Total File Ditemukan: ${dbmlFiles.length}`);
    console.log(`[NodeJS] Daftar File: ${dbmlFiles.join(', ')}`);

    if (dbmlFiles.length === 0) {
        console.log("⚠️ TIDAK ADA FILE DBML. Cek ekstensi file anda.");
        process.exit(0);
    }

    // 2. MULAI LOOPING
    (async () => {
        for (const file of dbmlFiles) {
            const name = path.parse(file).name;
            const inputPath = path.join(inputDir, file);
            const svgPath = path.join(outputDir, `${name}.temp.svg`);
            const pngPath = path.join(outputDir, `${name}.png`);

            console.log(`\n----------------------------------------`);
            console.log(`📄 Memeriksa: ${file}`);

            // Cek Skip
            if (fs.existsSync(pngPath) && !forceUpdate) {
                console.log(`⏭️  SKIP: ${name}.png (Sudah ada)`);
                continue; // Lanjut ke file berikutnya (Produksi/Kayu)
            }

            try {
                process.stdout.write(`⚙️  Rendering DBML ke SVG... `);
                
                // Render (ExecSync akan throw error jika dbml syntax salah)
                execSync(`npx dbml-renderer -i "${inputPath}" -o "${svgPath}"`, { 
                    stdio: 'pipe', // Tangkap error log
                    encoding: 'utf-8' 
                });
                console.log("OK");

                process.stdout.write(`🎨 Converting ke PNG... `);
                await sharp(svgPath).png({ quality: 100 }).toFile(pngPath);
                console.log("OK");

                // Hapus temp
                if (fs.existsSync(svgPath)) fs.unlinkSync(svgPath);

                console.log(`✅ SUKSES: ${name}.png Created.`);

            } catch (err) {
                console.log("❌ GAGAL!");
                console.error(`\n[ERROR LOG UNTUK FILE: ${file}]`);
                
                // Tampilkan pesan error asli dari dbml-renderer (misal: Syntax Error di baris 5)
                if (err.stdout) console.error("STDOUT:", err.stdout);
                if (err.stderr) console.error("STDERR:", err.stderr);
                if (err.message) console.error("MESSAGE:", err.message);
                
                console.log(`⚠️  Script akan lanjut ke file berikutnya...`);
            }
        }
        console.log(`\n----------------------------------------`);
        console.log("🏁 Selesai Semua.");
    })();

} catch (e) {
    console.error("FATAL ERROR (Folder tidak ditemukan?):", e.message);
}