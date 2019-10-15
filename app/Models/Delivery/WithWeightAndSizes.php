<?php

namespace App\Models\Delivery;

/**
 * Trait WithWeightAndSizes
 * @package App\Models\Delivery
 */
trait WithWeightAndSizes
{
    public function recalc(): void
    {
        $this->weight = $this->calcWeight();
        $volume = $this->calcVolume();
        $maxSide = $this->calcMaxSide();
        $maxSideName = $this->identifyMaxSideName($maxSide);
        $avgSide = pow($volume, 1/3);
        
        if ($maxSide <= $avgSide) {
            foreach (self::SIDES as $side) {
                $this[$side] = $avgSide;
            }
        } else {
            $otherSide = sqrt($volume/$maxSide);
            foreach (self::SIDES as $side) {
                if ($side == $maxSideName) {
                    $this[$side] = $maxSide;
                } else {
                    $this[$side] = $otherSide;
                }
            }
        }
        
        $this->save();
    }
}
