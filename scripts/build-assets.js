// Локальная сборка .min.css/.min.js для шаблона eporta (Этап 3 перфоманса).
// Node/esbuild нужны только здесь — на проде не устанавливаются, .min-файлы
// коммитятся в git как обычные статические файлы и деплоятся через git checkout.
const esbuild = require("esbuild");
const path = require("path");

const TPL = path.join(__dirname, "..", "local", "templates", "eporta");

const CSS_FILES = ["template_styles.css", "critical.css"];
const JS_FILES = ["app.js", "cart-kit.js", "compare.js", "kit.js"];

async function build() {
	for (const name of CSS_FILES) {
		const src = path.join(TPL, name);
		const out = path.join(TPL, name.replace(/\.css$/, ".min.css"));
		await esbuild.build({ entryPoints: [src], outfile: out, minify: true });
		console.log("built", out);
	}
	for (const name of JS_FILES) {
		const src = path.join(TPL, "assets", name);
		const out = path.join(TPL, "assets", name.replace(/\.js$/, ".min.js"));
		await esbuild.build({ entryPoints: [src], outfile: out, minify: true });
		console.log("built", out);
	}
}

build().catch((err) => {
	console.error(err);
	process.exit(1);
});
