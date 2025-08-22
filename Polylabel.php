<?php

namespace Polylabel;

class CellQueue {

	public $splPriorityQueue;

    public function __construct() {

		$this->splPriorityQueue = new \SplPriorityQueue();
		
	}

	public function push(Cell $cell) {

		$this->splPriorityQueue->insert($cell, $cell->max);

	}

	public function length() {

		return $this->splPriorityQueue->count();

	}

	/** @return Cell */
	public function pop() {

		return $this->splPriorityQueue->extract();

	}
	
} 

function polylabel($polygon, $precision = 1.0, $debugCallback = null) {

    $minX = $minY = INF;
    $maxX = $maxY = -INF;

    // find the bounding box of the outer ring
	for( $i = 0; $i < count($polygon[0]); $i++ ) {

		$p = $polygon[0][$i];
		if( $p[0] < $minX ) $minX = $p[0];
		if( $p[1] < $minY ) $minY = $p[1];
		if( $p[0] > $maxX ) $maxX = $p[0];
		if( $p[1] > $maxY ) $maxY = $p[1];

	}

	$width = $maxX - $minX;
	$height = $maxY - $minY;
	$cellSize = max($precision, min($width, $height));

	if( $cellSize == $precision ) {

		return [
			'x' => $minX,
			'y' => $minY,
			'distance' => 0
		];

	}

    // a priority queue of cells in order of their "potential" (max distance to polygon)
	$cellQueue = new CellQueue();

    // take centroid as the first best guess
    $bestCell = getCentroidCell($polygon);

    // second guess: bounding box centroid
    $bboxCell = new Cell($minX + $width / 2, $minY + $height / 2, 0, $polygon);
    if( $bboxCell->d > $bestCell->d) $bestCell = $bboxCell;

    $numProbes = 2;

    $potentiallyQueue = function($x, $y, $h) use ($precision, $debugCallback, $polygon, &$bestCell, &$numProbes, $cellQueue) {

        $cell = new Cell($x, $y, $h, $polygon);
        $numProbes++;
        if( $cell->max > $bestCell->d + $precision ) $cellQueue->push( $cell );

        // update the best cell if we found a better one
        if( $cell->d > $bestCell->d ) {
            $bestCell = $cell;
            if( $debugCallback ) $debugCallback( sprintf('found best %f after %d probes', round(1e4 * $cell->d) / 1e4, $numProbes) );
        }

    };

    // cover polygon with initial cells
    $h = $cellSize / 2;
    for( $x = $minX; $x < $maxX; $x += $cellSize ) {
        for( $y = $minY; $y < $maxY; $y += $cellSize ) {
			$potentiallyQueue($x + $h, $y + $h, $h);
        }
    }

    while( $cellQueue->length() ) {

        // pick the most promising cell from the queue
        $cell = $cellQueue->pop();

        // do not drill down further if there's no chance of a better solution
        if( $cell->max - $bestCell->d <= $precision ) break;

        // split the cell into four cells
        $h = $cell->h / 2;
        $potentiallyQueue($cell->x - $h, $cell->y - $h, $h);
        $potentiallyQueue($cell->x + $h, $cell->y - $h, $h);
        $potentiallyQueue($cell->x - $h, $cell->y + $h, $h);
        $potentiallyQueue($cell->x + $h, $cell->y + $h, $h);

    }

    if( $debugCallback ) {
        $debugCallback('num probes: ' . $numProbes);
        $debugCallback('best distance: ' . $bestCell->d);
    }
	
	return [
		'x' => $bestCell->x,
		'y' => $bestCell->y,
		'distance' => $bestCell->d
	];

}

class Cell {

	public $x;
	public $y;
	public $h;
	public $d;
	public $max;

	public function __construct($x, $y, $h, $polygon) {

		$this->x = $x; // cell center x
		$this->y = $y; // cell center y
		$this->h = $h; // half the cell size
		$this->d = pointToPolygonDist($x, $y, $polygon);
		$this->max = $this->d + $this->h * M_SQRT2;
		
	}

}

// signed distance from point to polygon outline (negative if point is outside)
function pointToPolygonDist( $x, $y, $polygon ) {

    $inside = false;
    $minDistSq = INF;

    for( $k = 0; $k < count($polygon); $k++ ) {

        $ring = $polygon[$k];

        for( $i = 0, $len = count($ring), $j = $len - 1; $i < $len; $j = $i++ ) {

            $a = $ring[$i];
            $b = $ring[$j];

            if(($a[1] > $y !== $b[1] > $y) &&
                ($x < ($b[0] - $a[0]) * ($y - $a[1]) / ($b[1] - $a[1]) + $a[0])) $inside = !$inside;

            $minDistSq = min($minDistSq, getSegDistSq($x, $y, $a, $b));

        }

    }

    return $minDistSq == 0 ? 0 : ($inside ? 1 : -1) * sqrt($minDistSq);

}

// get polygon centroid
function getCentroidCell($polygon) {

    $area = 0;
    $x = 0;
    $y = 0;
    $points = $polygon[0];

    for( $i = 0, $len = count($points), $j = $len - 1; $i < $len; $j = $i++ ) {

        $a = $points[$i];
        $b = $points[$j];
        $f = $a[0] * $b[1] - $b[0] * $a[1];
        $x += ($a[0] + $b[0]) * $f;
        $y += ($a[1] + $b[1]) * $f;
        $area += $f * 3;

    }

    // Create the fallback cell from the first vertex
    $fallbackCell = new Cell($points[0][0], $points[0][1], 0, $polygon);
    if( !$area ) return $fallbackCell;

    $centroid = new Cell($x / $area, $y / $area, 0, $polygon);

    if( $centroid->d < 0 ) return $fallbackCell;

    return $centroid;

}

// get squared distance from a point to a segment
function getSegDistSq($px, $py, $a, $b) {

    $x = $a[0];
    $y = $a[1];
    $dx = $b[0] - $x;
    $dy = $b[1] - $y;

	$denom = $dx * $dx + $dy * $dy;

    if( $denom > 0 ) {

        $t = (($px - $x) * $dx + ($py - $y) * $dy) / $denom;

        if( $t > 1 ) {

            $x = $b[0];
            $y = $b[1];

        } else if( $t > 0 ) {

            $x += $dx * $t;
            $y += $dy * $t;

        }

    }

    $dx = $px - $x;
    $dy = $py - $y;

    return $dx * $dx + $dy * $dy;
}
