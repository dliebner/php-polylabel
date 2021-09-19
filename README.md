# php-polylabel
PHP port of https://github.com/mapbox/polylabel

## Usage

```php

require('Polylabel.php');
use function \Polylabel\polylabel;

$polygon = [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]];

polylabel( $polygon, $precision = 50 ); // ['x' => 0.5, 'y' => 0.5, 'distance' => 0.5]

```