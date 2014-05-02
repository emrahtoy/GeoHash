/**
 * A little geohash library.
 * This library able to calculate neighbors by given hash or coordinates.
 * 
 * @param {String|Object} point can be a hash or an array of coordinates.
 * @param {type} customPrecision sets depth of algorithm
 * @throws {string} error 
 * @returns {Boolean}
 */
function GeoHash(point,customPrecision) {
    var error = false;
    if (point === undefined || point.length === 0 || typeof point === 'number') {
        error = 'You have to give hash as string or latitude and longitude coordinates in array. Given ' + typeof point + '.';
    }
    if ((typeof point !== 'object' && point.length === 2) || (typeof point!=='string' && ( typeof point[0] !== 'number' || typeof point[1] !== 'number'))) {
        error = 'You have to give number for each coordinate. Given latitude:' + point[0] + ' (' + typeof point[0] + '), longitude:' + point[1] + ' (' + typeof point[1] + ')';
    }

    if (error !== false) {
        (console) ? console.error(error) : alert(error);
        return false;
    }

    /**
     * Encodes given coordinates in depth of precision value.
     * 
     * @param {number} customPrecision sets depth of algorithm. Also directy affects the range of boundingbox and searchable safe area.
     * @param {number} latitude
     * @param {number} longitude
     * @returns {geohash|String|GeoHash.hash}
     */
    this.encode = function(customPrecision, latitude, longitude) {

        var latitude = (latitude !== undefined) ? latitude : coordinates.latitude;
        var longitude = (longitude !== undefined) ? longitude : coordinates.longitude;
        var is_even = 1;
        //var i = 0;
        var lat = [];
        var lon = [];
        var bit = 0;
        var ch = 0;
        var precision = customPrecision || currentPrecision;
        hash = '';

        lat[0] = -90.0;
        lat[1] = 90.0;
        lon[0] = -180.0;
        lon[1] = 180.0;

        while (hash.length < precision) {
            if (is_even) {
                mid = (lon[0] + lon[1]) / 2;
                if (longitude > mid) {
                    ch |= BITS[bit];
                    lon[0] = mid;
                } else
                    lon[1] = mid;
            } else {
                mid = (lat[0] + lat[1]) / 2;
                if (latitude > mid) {
                    ch |= BITS[bit];
                    lat[0] = mid;
                } else
                    lat[1] = mid;
            }

            is_even = !is_even;
            if (bit < 4)
                bit++;
            else {
                hash += BASE32[ch];
                bit = 0;
                ch = 0;
            }
        }
        this.decode(hash,'justBox');
        coordinates = {'latitude': latitude, 'longitude': longitude};
        return hash;
    };

    /**
     * Decodes given hash.
     * 
     * @param {string} geohash
     * @param {boolean} justBox Sometime we only need the box instead of setting coordinates center of boundingbox ( after encoding )
     * @returns {GeoHash.box}
     */
    this.decode = function(geohash,justBox) {
        hash = (geohash !== undefined) ? geohash : hash;
        var is_even = 1;
        var lat = [];
        var lon = [];
        lat[0] = -90.0;
        lat[1] = 90.0;
        lon[0] = -180.0;
        lon[1] = 180.0;
        lat_err = 90.0;
        lon_err = 180.0;

        for (i = 0; i < hash.length; i++) {
            c = hash[i];
            cd = BASE32.indexOf(c);
            for (j = 0; j < 5; j++) {
                mask = BITS[j];
                if (is_even) {
                    lon_err /= 2;
                    refine_interval(lon, cd, mask);
                } else {
                    lat_err /= 2;
                    refine_interval(lat, cd, mask);
                }
                is_even = !is_even;
            }
        }
        
        lat[2] = (lat[0] + lat[1]) / 2;
        lon[2] = (lon[0] + lon[1]) / 2;
        box = {'latitude': lat, 'longitude': lon};
        if(justBox) return box;
        coordinates = {'latitude': lat[2], 'longitude': lon[2]};
        return box;
    };

    /**
     * Calculates neighbors of current bounding box including itself as center box
     * 
     * @returns {GeoHash.neighbor}
     */
    this.neighbors = function() {
        neighbors = {
            'top': new GeoHash(calculateAdjacent(hash, 'top'),currentPrecision),
            'bottom': new GeoHash(calculateAdjacent(hash, 'bottom'),currentPrecision),
            'right': new GeoHash(calculateAdjacent(hash, 'right'),currentPrecision),
            'left': new GeoHash(calculateAdjacent(hash, 'left'),currentPrecision)
        };
        neighbors.topleft = new GeoHash(calculateAdjacent(neighbors.left.getHash(), 'top'),currentPrecision);
        neighbors.topright = new GeoHash(calculateAdjacent(neighbors.right.getHash(), 'top'),currentPrecision);
        neighbors.bottomright = new GeoHash(calculateAdjacent(neighbors.right.getHash(), 'bottom'),currentPrecision);
        neighbors.bottomleft = new GeoHash(calculateAdjacent(neighbors.left.getHash(), 'bottom'),currentPrecision);

        neighbors.center = this;

        return neighbors;
    };

    this.corners = function() {
        return {
            'topleft': {'latitude': box.latitude[0], 'longitude': box.longitude[0]},
            'bottomleft': {'latitude': box.latitude[0], 'longitude': box.longitude[1]},
            'topright': {'latitude': box.latitude[1], 'longitude': box.longitude[0]},
            'bottomright': {'latitude': box.latitude[1], 'longitude': box.longitude[1]},
            'center': {'latitude': (box.latitude[0] + box.latitude[1]) / 2, 'longitude': (box.longitude[0] + box.longitude[1]) / 2
            }
        };
    };

    /**
     * Gives all the private variables
     * 
     * @returns {Array} Returns hash, coordinates, box and neighbors
     */
    this.toString = function() {
        return [hash, coordinates, box, neighbors];
    };

    /** 
     * Gives range of bounding box
     * 
     * @returns {Number} Returns range in meters ( rounded )
     */
    this.range = function() {
        var point1 = this.corners().topleft;
        var point2 = this.corners().topright;

        var point3 = this.corners().topleft;
        var point4 = this.corners().bottomleft;

        var latitudeDistance = getDistance(point1, point2);
        var longitudeDistance = getDistance(point3, point4);

        //returns minor range in meters
        return (latitudeDistance < longitudeDistance) ? latitudeDistance / 2 : longitudeDistance / 2;
    };

    /**
     * Gives range of searchable safe area including neighbors
     * 
     * @returns {Number} Returns range in meters ( rounded )
     */
    this.searchRange = function() {
        var point1 = this.neighbors().topleft.corners().topleft;
        var point2 = this.neighbors().topright.corners().topright;

        var point3 = this.neighbors().topleft.corners().topleft;
        var point4 = this.neighbors().bottomleft.corners().bottomleft;

        var latitudeDistance = getDistance(point1, point2);
        var longitudeDistance = getDistance(point3, point4);

        //returns minor range in meters
        return (latitudeDistance < longitudeDistance) ? latitudeDistance / 2 : longitudeDistance / 2;
    };
    
    /**
     * Gives hash.
     * 
     * @returns {geohash|String|GeoHash.hash} Returns current hash.
     */
    this.getHash=function(){
        return hash;
    };

    /*
     * Private variables 
     */
    var hash, coordinates, box, neighbors, error, currentPrecision;

    /*
     * private constants
     */
    var BITS = [16, 8, 4, 2, 1];

    var BASE32 = "0123456789bcdefghjkmnpqrstuvwxyz";
    var NEIGHBORS = {
        right: {even: "bc01fg45238967deuvhjyznpkmstqrwx"},
        left: {even: "238967debc01fg45kmstqrwxuvhjyznp"},
        top: {even: "p0r21436x8zb9dcf5h7kjnmqesgutwvy"},
        bottom: {even: "14365h7k9dcfesgujnmqp0r2twvyx8zb"}
    };
    var BORDERS = {
        right: {even: "bcfguvyz"},
        left: {even: "0145hjnp"},
        top: {even: "prxz"},
        bottom: {even: "028b"}
    };

    NEIGHBORS.bottom.odd = NEIGHBORS.left.even;
    NEIGHBORS.top.odd = NEIGHBORS.right.even;
    NEIGHBORS.left.odd = NEIGHBORS.bottom.even;
    NEIGHBORS.right.odd = NEIGHBORS.top.even;

    BORDERS.bottom.odd = BORDERS.left.even;
    BORDERS.top.odd = BORDERS.right.even;
    BORDERS.left.odd = BORDERS.bottom.even;
    BORDERS.right.odd = BORDERS.top.even;

    /**
     * Private functions
     */

    function refine_interval(interval, cd, mask) {
        if (cd & mask)
            interval[0] = (interval[0] + interval[1]) / 2;
        else
            interval[1] = (interval[0] + interval[1]) / 2;
    }

    /**
     * Gives radian of given degree.
     * 
     * @param {Number} x
     * @returns {Number}
     */
    function rad(x) {
        return x * Math.PI / 180;
    }

    /**
     * Distance calculation (Haversine formula)
     * 
     * @param {Coordinate} point1
     * @param {Coordinate} point2
     * @returns {Number}
     */
    function getDistance(point1, point2) {
        var R = 6378137; // Earth’s mean radius in meter
        var dLat = rad(point2.latitude - point1.latitude);
        var dLong = rad(point2.longitude - point1.longitude);
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(rad(point1.latitude)) * Math.cos(rad(point2.latitude)) *
                Math.sin(dLong / 2) * Math.sin(dLong / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        var d = R * c;
        return Math.round(d); // returns the distance in meter
    }


    /**
     * Gives hashes of neighbors of given hash.
     * 
     * @param {String} srcHash
     * @param {String} dir
     * @returns {String|GeoHash.BASE32}
     */
    function calculateAdjacent(srcHash, dir) {
        srcHash = srcHash.toLowerCase();
        var lastChr = srcHash.charAt(srcHash.length - 1);
        var type = (srcHash.length % 2) ? 'odd' : 'even';
        var base = srcHash.substring(0, srcHash.length - 1);
        if (BORDERS[dir][type].indexOf(lastChr) !== -1)
            base = calculateAdjacent(base, dir);
        return base + BASE32[NEIGHBORS[dir][type].indexOf(lastChr)];
    }

    /*
     * Initializing
     */
    if (point !== undefined) {
        if (typeof point === 'string') {
            hash = point;
            this.decode();
        } else {
            coordinates = {'latitude': point[0], 'longitude': point[1]};
            currentPrecision=customPrecision || 12;
            this.encode();
        }
    }
}


