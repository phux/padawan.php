<?php

namespace Padawan\Parser;

use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Name;
use Padawan\Domain\Project\FQN;
use Padawan\Domain\Project\Node\Uses;

class NamespaceParser {
    public function parse(Namespace_ $node) {
        if ($node->name instanceof Name) {
            $fqn = new FQN($node->name->parts);
            $this->uses->setFQCN($fqn);
        }
    }
    public function setUses(Uses $uses = null) {
        $this->uses = $uses;
    }
    private $uses;
}
