// Copies the PHP support backend (backend/web) into the static export (out/) so
// one upload of out/ deploys the site and the API together. Secrets are never
// copied: the production config file is uploaded separately (see
// docs/support-setup.md).
import { cpSync, existsSync, readdirSync, statSync } from "node:fs";
import { basename, join, relative } from "node:path";

const root = process.cwd();
const source = join(root, "backend", "web");
const target = join(root, "out");

const excluded = (path) => {
  const name = basename(path);

  return (
    name === "config.php" ||
    name === "berycode-support-config.php" ||
    name.endsWith(".local.php") ||
    name === ".DS_Store"
  );
};

if (!existsSync(target)) {
  console.error(
    "copy-support-backend: out/ does not exist; run next build first.",
  );
  process.exit(1);
}

cpSync(source, target, {
  recursive: true,
  filter: (path) => !excluded(path),
});

let count = 0;
const walk = (dir) => {
  for (const entry of readdirSync(dir)) {
    const path = join(dir, entry);
    if (statSync(path).isDirectory()) walk(path);
    else if (!excluded(path)) count++;
  }
};
walk(source);

console.log(
  `copy-support-backend: copied ${count} files from ${relative(root, source)} into ${relative(root, target)}/`,
);
