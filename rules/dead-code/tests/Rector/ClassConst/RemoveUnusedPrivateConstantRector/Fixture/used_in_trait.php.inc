<?php

namespace Rector\DeadCode\Tests\Rector\ClassConst\RemoveUnusedPrivateConstantRector\UsedInTrait;

final class KeepStaticConstant
{
    use aTrait;
    private const SOME_CONSTANT = 5;

}

trait aTrait {
    public function foo(){
        return self::SOME_CONSTANT;
    }
}
