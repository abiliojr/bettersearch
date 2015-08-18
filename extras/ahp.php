<?php

/*

	This is a q&d code used to calculate the factor weights for the ranking formula
	
	Basically, it uses the AHP technique to calculate the values. It doesn't include
	consistency checks.
	For more information, refer to: https://en.wikipedia.org/wiki/Analytic_hierarchy_process

	By: Abilio Marques <https://github.com/abiliojr>

*/

/*

	The next matrix holds the pairwise comparison for each factor.

	AHP defines the following scale for importance comparison:
		1 equal, 3 moderate, 5 strong, 7 very strong, 9 extreme

	For example:
		in row 1 column 5, you can read:
		having a lot of views has a very strong importance over being fresh.

		in row 1 column 6 you can read:
		perfectly matching the sting has a strong importance over having a lot of views
		(hence the reciprocal)

	Feel free to modify it to suit your needs. The fields currently holding a zero will
	be automatically calculated by the software, so please leave them untouched.

*/



$input = [
                  /*     views,      edits,    searchs,       refs, freshness,     string */
       'views' => [        1/1,        5/1,        3/1,        5/1,       7/1,        1/5],
       'edits' => [          0,        1/1,        1/5,        1/3,       5/1,        1/9],
     'searchs' => [          0,          0,        1/1,        5/1,       7/1,        1/3],
        'refs' => [          0,          0,          0,        1/1,       5/1,        1/5],
   'freshness' => [          0,          0,          0,          0,       1/1,        1/9],
      'string' => [          0,          0,          0,          0,         0,        1/1]
];


$SCALE_FACTOR = 10000;
$ITERATIONS = 5;


function matrix_power($matrix) {
	$result = [[]];
	$len = count($matrix);

	for ($i = 0; $i < $len; $i++) {
		for ($j = 0; $j < $len; $j++) {
			$result[$i][$j] = 0;
			for ($k = 0; $k < $len; $k++) {
				$result[$i][$j] += $matrix[$i][$k] * $matrix[$k][$j];
			}
		}
	}
	return $result;
}


function eigenvector($matrix) {
	$result = [];
	$len = count($matrix);
	$sum = 0.0;

	for ($i = 0; $i < $len; $i++) {
		$result[$i] = 0.0;
		for ($j = 0; $j < $len; $j++) {
			$result[$i] += $matrix[$i][$j];
		}
		$sum += $result[$i];
	}

	// normalize
	for ($i = 0; $i < $len; $i++) $result[$i] /= $sum;
	return $result;
}


function complete_input_matrix(&$matrix) {
	$len = count($matrix);
	for ($i = 0; $i < $len; $i++) {
		for ($j = $i + 1; $j < $len; $j++) {
			$matrix[$j][$i] = 1 / $matrix[$i][$j];
		}
	}
}

function get_indexed_matrix($in) {
	$matrix = [];

	$keys = array_keys($in);

	$len = count($in[$keys[0]]);

	for ($i = 0; $i < $len; $i++) {
		$matrix[$i] = $in[$keys[$i]];		
	}

	return $matrix;
}

function compute_values($matrix) {
	global $ITERATIONS;

	for ($i = 0; $i < $ITERATIONS; $i++) {
		$matrix = matrix_power($matrix);
	}

	return eigenvector($matrix);
}

function scale(&$vector) {
	global $SCALE_FACTOR;
	$len = count($vector);

	for ($i = 0; $i < $len; $i++) $vector[$i] *= $SCALE_FACTOR;
}

function print_result_vector($vector, $names) {
	$len = count($vector);

	for ($i = 0; $i < $len; $i++) printf("%s: %.3f\n",$names[$i], $vector[$i]);
}

function main() {
	global $input;

	$matrix = get_indexed_matrix($input);
	complete_input_matrix($matrix);
	$result = compute_values($matrix);
	scale($result);
	print_result_vector($result, array_keys($input));
}

main();
