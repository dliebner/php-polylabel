<?php

namespace PolylabelTest;

require('../Polylabel.php');

use function \Polylabel\polylabel;

$water1 = json_decode(file_get_contents('fixtures/water1.json'));
$water2 = json_decode(file_get_contents('fixtures/water2.json'));

class Test {

	public $name;
	public $polygon;
	public $precision;
	public $expectedResult;

	public function __construct($name, $polygon, $expectedResult, $precision = 1.0) {

		$this->name = $name;
		$this->polygon = $polygon;
		$this->precision = $precision;
		$this->expectedResult = $expectedResult;

	}

	public function test() {

		echo 'Testing ' . $this->name . "<br>\n";

		$result = polylabel($this->polygon, $this->precision, function($debugOutput) {

			echo "\tdebug: " . $debugOutput . "<br>\n";

		});

		if( $result !== $this->expectedResult ) {

			echo $this->name . ' failed:';

			echo nl2br(print_r([
				'result' => $result,
				'expectedResult' => $this->expectedResult
			], true));

		} else {

			echo $this->name . ' passed.';

		}

		echo "<br><br>\n\n";

	}

}

$tests = [
	new Test('finds pole of inaccessibility for water1 and precision 1', $water1, [
		'x' => 3865.85009765625,
		'y' => 2124.87841796875,
		'distance' => 288.8493574779127
	]),
	new Test('finds pole of inaccessibility for water1 and precision 50', $water1, [
		'x' => 3854.296875,
		'y' => 2123.828125,
		'distance' => 278.5795872381558
	], 50),
	new test('finds pole of inaccessibility for water2 and default precision 1', $water2, [
		'x' => 3263.5,
		'y' => 3263.5,
		'distance' => 960.5
	]),
	new Test('works on degenerate polygons', json_decode('[[[0, 0], [1, 0], [2, 0], [0, 0]]]'), [
		'distance' => 0
	])
];

foreach( $tests as $test ) {

	$test->test();

}