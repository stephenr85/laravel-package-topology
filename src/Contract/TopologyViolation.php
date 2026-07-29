<?php

declare(strict_types=1);

namespace Rushing\PackageTopology\Contract;

/**
 * A single legible finding — the readonly output of the evaluator. It names the
 * rule kind that failed, a human `detail` sentence, the offending endpoints (a
 * package pair, a package + file, or just a package), and the declared `because`
 * rationale so a failing suite reads as a hierarchy statement, not a stack trace.
 */
final readonly class TopologyViolation
{
    public function __construct(
        public RuleKind $kind,
        public string $detail,
        public ?string $subject = null,
        public ?string $object = null,
        public ?string $because = null,
    ) {}

    /** A one-line human rendering: `[kind] detail (because …)`. */
    public function message(): string
    {
        $because = $this->because !== null && $this->because !== ''
            ? " (because {$this->because})"
            : '';

        return "[{$this->kind->value}] {$this->detail}{$because}";
    }
}
