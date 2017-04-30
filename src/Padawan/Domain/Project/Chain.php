<?php

namespace Padawan\Domain\Project;

class Chain
{
    public function __construct(Chain $child = null, $name = '', $type = '')
    {
        $this->child = $child;
        $this->name = $name;
        $this->type = $type;
        if ($child instanceof Chain) {
            $child->setParent($this);
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return Chain
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return Chain
     */
    public function getChild()
    {
        return $this->child;
    }

    public function setParent(Chain $parent)
    {
        $this->parent = $parent;
    }

    /** @var string */
    private $type;
    /** @var string */
    private $name;
    /** @var Chain|null */
    private $parent;
    /** @var Chain|null */
    private $child;
}
