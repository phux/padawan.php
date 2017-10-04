<?php
$pepe = 1;
$popo = [];

/**
 * @return string[][][]
 */
function hoge(): array
{
    global $popo;
    static $p = 1;
    $o = 1;
    static $g = [];
}

class Foo
{
    /**
     * @return DateTime[]
     */
    public function hoge(): array
    {
        global $pepe;
        $q = $this->hoge();
        foreach ($q as $qq) {
            $p = function () use ($qq)
            {
            };
        }
    }
}

// foreach (hoge() as $x) {
//     foreach ($x as $y) {
//         foreach ($y as $z) {
//         }
//     }
// }

foreach (hoge() as $a) {
    foreach ($a as list($b, $c)) {
        foreach ($b as [$d, $e]) {
            $fp = @fopen();
        }
        foreach ($c as [$f, $g]) {
        }
    }
}

$poe = [];
unset($f, $poe['po'], $g, $OMMC);


