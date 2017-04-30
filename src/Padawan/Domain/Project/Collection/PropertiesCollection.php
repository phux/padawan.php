<?php

namespace Padawan\Domain\Project\Collection;

use Padawan\Domain\Project\Node\ClassProperty;
use Padawan\Domain\Project\Node\ClassData;

class PropertiesCollection
{
    /**
     * @param ClassData $class
     */
    public function __construct($class)
    {
        $this->class = $class;
    }

    public function add(ClassProperty $prop)
    {
        $this->map[$prop->name] = $prop;
    }

    /**
     * @param Specification $spec
     * @return array
     */
    public function all(Specification $spec = null)
    {
        if ($spec === null) {
            $spec = new Specification();
        }
        $props = [];
        foreach ($this->map as $prop) {
            if (!$spec->satisfy($prop)) {
                continue;
            }
            $props[$prop->name] = $prop;
        }
        $parent = $this->class->getParent();
        if ($parent instanceof ClassData) {
            $props = array_merge(
                $parent->properties->all(new Specification(
                    $spec->getParentMode(),
                    $spec->isStatic(),
                    $spec->isMagic()
                )),
                $props
            );
        }
        ksort($props);

        return $props;
    }

    /**
     * @param string $propName
     * @param Specification $spec
     * @return null|mixed
     */
    public function get($propName, Specification $spec = null)
    {
        if ($spec === null) {
            $spec = new Specification('private', 2, false);
        }
        if (array_key_exists($propName, $this->map)) {
            $prop = $this->map[$propName];
            if ($spec->satisfy($prop)) {
                return $prop;
            }

            return null;
        }
        $parent = $this->class->getParent();
        if ($parent instanceof ClassData) {
            return $parent->properties->get(
                $propName,
                new Specification(
                    $spec->getParentMode(),
                    $spec->isStatic(),
                    $spec->isMagic()
                )
            );
        }
    }

    /** @var array */
    private $map = [];
    /** @var ClassData */
    private $class;
}
