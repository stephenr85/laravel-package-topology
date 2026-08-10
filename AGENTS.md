> You are in **rushing/php-package-topology** — a declarative package-topology substrate for composer monorepos, riding `rushing/php-graphine` to assert a repo's architecture (package-require graph + source-import boundary) as one `TopologyContract` instead of hand-rolled fitness tests.

Leaf Composer package: one `TopologyContract` spanning two axes — the composer-require graph (direct edges,
reachability, layering, cycles) and the source-import boundary (AST namespace guard) — plus a testing kit so
a consuming repo declares its architecture instead of hand-rolling fitness tests.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
