// Локальная оптимизация статических картинок шаблона eporta (Этап 5 перфоманса).
// В отличие от товарных фото (Этап 2, динамическая WebP-конвертация на сервере через GD —
// nginx не умеет webp "на лету"), эти файлы — статика в git (плитки категорий на главной,
// "хиты"/коллекции), поэтому конвертируем один раз локально и коммитим готовые .webp рядом
// с оригиналом. JPG-оригинал тоже пересжимаем/уменьшаем — он остаётся fallback для браузеров
// без webp (<picture><source type="image/webp">) и как источник для будущих правок.
const sharp = require("sharp");
const path = require("path");

const IMG_DIR = path.join(__dirname, "..", "local", "templates", "eporta", "assets", "img");

// Целевая ширина взята с запасом (~1.5-2x) от максимального реального размера показа
// на десктопе из Lighthouse-отчёта (самая крупная плитка — 609px), чтобы прикрыть retina
// без раздувания веса обратно к исходным 900-1250px.
const TARGETS = {
	// Уточнено 2026-08-08 по свежему mobile-Lighthouse (были ещё оверсайз даже после первого
	// прохода 05.08 — этот проход резал только "шире исходника", не под реальный показ).
	"cat-mezh.jpg": 700,   // квадратная крупная плитка, показ до 609x609
	"cat-skryt.jpg": 500,  // показ 330x330
	"cat-razdv.jpg": 550,  // показ 395x294
	"cat-vhod.jpg": 650,   // показ 441x294
	"cat-arki.jpg": 600,   // показ 392x294
	"cat-furn.jpg": 900,   // высокая плитка, показ 892x609 (по высоте) — уже ок, не режем
	"hit-1.jpg": 700,
	"hit-2.jpg": 700,
	"hit-3.jpg": 700,
	"hit-4.jpg": 700,
	"hit-5.jpg": 700,
	"hit-6.jpg": 700,
	"hit-7.jpg": 700,
	"hit-8.jpg": 700,
	"sim-1.jpg": 700,
};

async function run() {
	for (const [name, targetWidth] of Object.entries(TARGETS)) {
		const src = path.join(IMG_DIR, name);
		const base = name.replace(/\.jpg$/, "");
		const jpgOut = path.join(IMG_DIR, name); // перезаписываем оригинал уменьшенной/пересжатой версией
		const webpOut = path.join(IMG_DIR, base + ".webp");

		const img = sharp(src).rotate(); // rotate() без аргументов — уважает EXIF-ориентацию
		const meta = await img.metadata();
		const resizeOpts = meta.width && meta.width > targetWidth ? { width: targetWidth } : null;

		const pipeline = resizeOpts ? sharp(src).rotate().resize(resizeOpts) : sharp(src).rotate();
		await pipeline.clone().jpeg({ quality: 82, mozjpeg: true }).toFile(jpgOut + ".tmp");
		await pipeline.clone().webp({ quality: 80 }).toFile(webpOut);

		const fs = require("fs");
		fs.renameSync(jpgOut + ".tmp", jpgOut);
		console.log(name, "->", targetWidth, "px, webp+jpg готовы");
	}
}

run().catch((err) => {
	console.error(err);
	process.exit(1);
});
