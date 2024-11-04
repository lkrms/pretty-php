<?php
uasort(
    $members,
    fn($a, $b) =>
        ($a->Line ?? \PHP_INT_MAX) <=> ($b->Line ?? \PHP_INT_MAX)
            ?: ($a->InheritedFrom[0] ?? '') <=> ($b->InheritedFrom[0] ?? '')
            ?: ($_a = $this->getInheritedMemberData($a, $factory))->Class->getFqcn() <=>
                ($_b = $this->getInheritedMemberData($b, $factory))->Class->getFqcn()
            ?: ($_a->Line ?? \PHP_INT_MAX) <=> ($_b->Line ?? \PHP_INT_MAX)
            ?: strcasecmp($a->Name, $b->Name),
);
