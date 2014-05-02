<?php

Namespace GeoHash {

/**
 * A little geohash library.
 * This library able to calculate neighbors by given hash or coordinates.
 *
 */
class GeoHash {

    private $hash, $coordinates, $box, $neighbors, $currentPrecision;
    private $error = false;
    private $BITS = [16, 8, 4, 2, 1];
    private $BASE32 = "0123456789bcdefghjkmnpqrstuvwxyz";
    private $NEIGHBORS = [
        'right' => ['even' => "bc01fg45238967deuvhjyznpkmstqrwx", 'odd' => "p0r21436x8zb9dcf5h7kjnmqesgutwvy"],
        'left' => ['even' => "238967debc01fg45kmstqrwxuvhjyznp", 'odd' => "14365h7k9dcfesgujnmqp0r2twvyx8zb"],
        'top' => ['even' => "p0r21436x8zb9dcf5h7kjnmqesgutwvy", 'odd' => "bc01fg45238967deuvhjyznpkmstqrwx"],
        'bottom' => ['even' => "14365h7k9dcfesgujnmqp0r2twvyx8zb", 'odd' => "238967debc01fg45kmstqrwxuvhjyznp"]
    ];
    private $BORDERS = [
        'right' => ['even' => "bcfguvyz", 'odd' => "prxz"],
        'left' => ['even' => "0145hjnp", 'odd' => "028b"],
        'top' => ['even' => "prxz", 'odd' => "bcfguvyz"],
        'bottom' => ['even' => "028b", 'odd' => "0145hjnp"]
    ];

    /**
     * 
     * @param Array|String $point Point is the exact point on the map. It can be string (hash) or array of coordinates.
     * @param Integer $customPrecision Precision means detail or depth. More precision means little range.
     * @throws ErrorException This class throws this error if the given point is not a proper one.
     */
    public function __construct($point, $customPrecision=false) {
        if (empty($point) || ( is_array($point) && count($point) < 2) || is_int($point)) {
            $this->error = 'You have to give hash as string or latitude and longitude coordinates in array. Given ' . gettype($point) . '.';
        }
        if (is_array($point) && ((!is_int($point[0]) || !is_int($point[1])) && (!is_double($point[0]) || !is_double($point[1])) )) {
            $this->error = 'You have to give number for each coordinate. Given latitude:' . $point[0] . ' (' . gettype($point[0]) . '), longitude:' . $point[1] . ' (' . gettype($point[1]) . ')';
        }

        if ($this->error !== false) {
            throw new ErrorException($this->error);
        }

        if (!empty($point)) {
            if (gettype($point) === 'string') {
                $this->hash = $point;
                $this->decode();
            } else {
                $this->coordinates = ['latitude' => $point[0], 'longitude' => $point[1]];
                $this->currentPrecision= ($customPrecision)?$customPrecision:12;
                $this->encode();
            }
        }
    }

    
    /**
     * Encodes given coordinates in depth of precision value.
     *
     * @param {number} customPrecision sets depth of algorithm. Also directy affects the range of boundingbox and searchable safe area.
     * @param {number} latitude
     * @param {number} longitude
     * @returns {geohash|String|GeoHash.hash}
     */
    public function encode($customPrecision=false, $latitude=false, $longitude=false) {
        $latitude = (!empty($latitude)) ? $latitude : $this->coordinates['latitude'];
        $longitude = (!empty($longitude)) ? $longitude : $this->coordinates['longitude'];
        $is_even = 1;
        $lat = [];
        $lon = [];
        $bit = 0;
        $ch = 0;
        $precision = ($customPrecision)? $customPrecision : $this->currentPrecision;
        $hash = '';
        $lat[0] = -90.0;
        $lat[1] = 90.0;
        $lon[0] = -180.0;
        $lon[1] = 180.0;
        while (strlen($hash) < $precision) {
            if ($is_even) {
                $mid = ($lon[0] + $lon[1]) / 2;
                if ($longitude > $mid) {
                    $ch |= $this->BITS[$bit];
                    $lon[0] = $mid;
                } else {
                    $lon[1] = $mid;
                }
            } else {
                $mid = ($lat[0] + $lat[1]) / 2;
                if ($latitude > $mid) {
                    $ch |= $this->BITS[$bit];
                    $lat[0] = $mid;
                } else {
                    $lat[1] = $mid;
                }
            }
            $is_even = ($is_even==1)?0:1;
            if ($bit < 4) {
                $bit++;
            } else {
                $hash .= $this->BASE32[$ch];
                $bit = 0;
                $ch = 0;
            }
        }
        $this->hash = $hash;
        $this->decode($this->hash, 'justBox');
        $this->coordinates = ['latitude' => $latitude, 'longitude' => $longitude];
        return $this->hash;
    }

    /**
     * Decodes given hash.
     *
     * @param {string} geohash
     * @param {boolean} justBox Sometime we only need the box instead of setting coordinates center of boundingbox ( after encoding )
     * @returns {GeoHash.box}
     */
    public function decode($geohash=null, $justBox = false) {
        $hash = (!empty($geohash)) ? $geohash : $this->hash;
        $is_even = 1;
        $lat = [];
        $lon = [];
        $lat[0] = -90.0;
        $lat[1] = 90.0;
        $lon[0] = -180.0;
        $lon[1] = 180.0;
        $lat_err = 90.0;
        $lon_err = 180.0;

        for ($i = 0; $i < strlen($hash); $i++) {
            $c = substr($hash,$i,1);
            $cd = strpos($this->BASE32, $c);
            for ($j = 0; $j < 5; $j++) {
                $mask = $this->BITS[$j];
                if ($is_even) {
                    $lon_err /= 2;
                    $this->refine_interval($lon, $cd, $mask);
                } else {
                    $lat_err /= 2;
                    $this->refine_interval($lat, $cd, $mask);
                }
                $is_even = ($is_even==1)?0:1;
            }
        }
        $lat[2] = ($lat[0] + $lat[1]) / 2;
        $lon[2] = ($lon[0] + $lon[1]) / 2;
        $this->box = ['latitude' => $lat, 'longitude' => $lon];
        if ($justBox) {
            return $this->box;
        }
        $this->coordinates = ['latitude' => $lat[2], 'longitude' => $lon[2]];
        return $this->box;
    }

    /**
     * Calculates neighbors of current bounding box including itself as center box
     *
     * @returns {GeoHash.neighbor}
     */
    public function neighbors() {
        $this->neighbors = [
            'top' => new GeoHash($this->calculateAdjacent($this->hash, 'top'),$this->currentPrecision),
            'bottom' => new GeoHash($this->calculateAdjacent($this->hash, 'bottom'),$this->currentPrecision),
            'right' => new GeoHash($this->calculateAdjacent($this->hash, 'right'),$this->currentPrecision),
            'left' => new GeoHash($this->calculateAdjacent($this->hash, 'left'),$this->currentPrecision)
        ];
        
        $this->neighbors['topleft'] = new GeoHash($this->calculateAdjacent($this->neighbors['left']->getHash(), 'top'),$this->currentPrecision);
        $this->neighbors['topright'] = new GeoHash($this->calculateAdjacent($this->neighbors['right']->getHash(), 'top'),$this->currentPrecision);
        $this->neighbors['bottomright'] = new GeoHash($this->calculateAdjacent($this->neighbors['right']->getHash(), 'bottom'),$this->currentPrecision);
        $this->neighbors['bottomleft'] = new GeoHash($this->calculateAdjacent($this->neighbors['left']->getHash(), 'bottom'),$this->currentPrecision);
        $this->neighbors['center'] = $this;

        return $this->neighbors;
    }

    public function corners() {
        return [
            'topleft' => ['latitude' => $this->box['latitude'][0], 'longitude' => $this->box['longitude'][0]],
            'bottomleft' => ['latitude' => $this->box['latitude'][0], 'longitude' => $this->box['longitude'][1]],
            'topright' => ['latitude' => $this->box['latitude'][1], 'longitude' => $this->box['longitude'][0]],
            'bottomright' => ['latitude' => $this->box['latitude'][1], 'longitude' => $this->box['longitude'][1]],
            'center' => ['latitude' => ($this->box['latitude'][0] + $this->box['latitude'][1]) / 2, 'longitude' => ($this->box['longitude'][0] + $this->box['longitude'][1]) / 2]
        ];
    }

    /**
     * Gives all the private variables
     *
     * @returns {Array} Returns hash, coordinates, box and neighbors
     */
    public function toString() {
        return [$this->hash, $this->coordinates, $this->box, $this->neighbors];
    }

    /**
     * Gives range of bounding box
     *
     * @returns {Number} Returns range in meters ( rounded )
     */
    public function range() {
        $all_corners = $this->corners();
        $point1 = $all_corners['topleft'];
        $point2 = $all_corners['topright'];

        $point3 = $point1;
        $point4 = $all_corners['bottomleft'];

        $latitudeDistance = $this->getDistance($point1, $point2);
        $longitudeDistance = $this->getDistance($point3, $point4);

//returns minor range in meters
        return ($latitudeDistance < $longitudeDistance) ? $latitudeDistance / 2 : $longitudeDistance / 2;
    }

    /**
     * Gives range of searchable safe area including neighbors
     *
     * @returns {Number} Returns range in meters ( rounded )
     */
    public function searchRange() {

        $all_neighbors = $this->neighbors();

        $top_left_neighbor = $all_neighbors['topleft'];
        $top_left_neighbor_corners = $top_left_neighbor->corners();
        $point1 = $top_left_neighbor_corners['topleft'];

        $top_right_neighbor = $all_neighbors['topright'];
        $top_right_neighbor_corners = $top_right_neighbor->corners();
        $point2 = $top_right_neighbor_corners['topright'];

        $bottom_left_neighbor = $all_neighbors['bottomleft'];
        $bottom_left_neighbor_corners = $bottom_left_neighbor->corners();
        $point4 = $bottom_left_neighbor_corners['bottomleft'];

        $point3 = $point1;

        $latitudeDistance = $this->getDistance($point1, $point2);
        $longitudeDistance = $this->getDistance($point3, $point4);

//returns minor range in meters
        return ($latitudeDistance < $longitudeDistance) ? $latitudeDistance / 2 : $longitudeDistance / 2;
    }

    /**
     * Gives hash.
     *
     * @returns {geohash|String|GeoHash.hash} Returns current hash.
     */
    public function getHash() {
        return $this->hash;
    }

    /**
     * Private functions
     */
    private function refine_interval(&$interval, $cd, $mask) {

        if ($cd & $mask) {
            $interval[0] = ($interval[0] + $interval[1]) / 2;
        } else {
            $interval[1] = ($interval[0] + $interval[1]) / 2;
        }
    }

    /**
     * Gives radian of given degree.
     *
     * @param {Number} x
     * @returns {Number}
     */
    private function rad($x) {
        return $x * pi() / 180;
    }

    /**
     * Distance calculation (Haversine formula)
     *
     * @param {Coordinate} point1
     * @param {Coordinate} point2
     * @returns {Number}
     */
    private function getDistance($point1, $point2) {
        $R = 6378137; // Earth’s mean radius in meter
        $dLat = $this->rad($point2['latitude'] - $point1['latitude']);
        $dLong = $this->rad($point2['longitude'] - $point1['longitude']);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos($this->rad($point1['latitude'])) * cos($this->rad($point2['latitude'])) * sin($dLong / 2) * sin($dLong / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $d = $R * $c;
        return round($d); // returns the distance in meter
    }

    /**
     * Gives hashes of neighbors of given hash.
     *
     * @param {String} srcHash
     * @param {String} dir
     * @returns {String|GeoHash.BASE32}
     */
    private function calculateAdjacent($srcHash, $dir) {
        $srcHash = strtolower($srcHash);
        $lastChr = substr($srcHash, strlen($srcHash) - 1, 1);
        $type = (strlen($srcHash) % 2) ? 'odd' : 'even';
        $base = substr($srcHash, 0, strlen($srcHash) - 1);
        if (strpos($this->BORDERS[$dir][$type], $lastChr) !== false) {
            $base = $this->calculateAdjacent($base, $dir);
        }
        return $base . substr($this->BASE32,strpos($this->NEIGHBORS[$dir][$type], $lastChr),1);
    }

}

}