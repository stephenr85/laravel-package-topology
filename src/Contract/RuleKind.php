<?php

declare(strict_types=1);

namespace Rushing\PackageTopology\Contract;

use Rushing\PackageTopology\Sources\ComposerManifestGraphSource;

/**
 * The kinds of topology rule a {@see TopologyContract} can carry — spanning the
 * two axes the substrate enforces:
 *
 *   - PACKAGE-GRAPH axis (composer `require` edges): every kind except
 *     {@see self::SourceNeverReferences}. Answered by the graphine spine hydrated
 *     from {@see ComposerManifestGraphSource}.
 *   - SOURCE-IMPORT axis (namespace references in a package's `src/`):
 *     {@see self::SourceNeverReferences}. Answered by graphine's AST `SeamGuard`.
 *
 * Direct-edge rules ({@see self::RequiredDirectEdge}/{@see self::ForbiddenDirectEdge})
 * are a *direct* `require` claim (one hop, `maxDepth: 1`); the reachability kinds
 * ({@see self::ForbiddenReachable}/{@see self::DownOnly}/{@see self::LayerOrder})
 * are transitive (`shortestPath`); {@see self::Acyclic} is `detectCycles`.
 */
enum RuleKind: string
{
    case RequiredDirectEdge = 'required_direct_edge';
    case ForbiddenDirectEdge = 'forbidden_direct_edge';
    case ForbiddenReachable = 'forbidden_reachable';
    case DownOnly = 'down_only';
    case LayerOrder = 'layer_order';
    case Acyclic = 'acyclic';
    case MustBeInstalled = 'must_be_installed';
    case SourceNeverReferences = 'source_never_references';

    /** Is this rule checked on the source-import axis (SeamGuard) rather than the package graph? */
    public function isSourceAxis(): bool
    {
        return $this === self::SourceNeverReferences;
    }
}
