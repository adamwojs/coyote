<?php

namespace Coyote\Services\Elasticsearch\Filters\Job;

use Coyote\Services\Geocoder\Location as GeocoderLocation;
use Coyote\Services\Elasticsearch\Filter;
use Coyote\Services\Elasticsearch\QueryBuilderInterface;

class LocationScore extends Filter
{
    const WEIGHT = 2;
    const SCALE = '10km';
    const OFFSET = '20km';

    /**
     * @param GeocoderLocation|null $value
     */
    public function __construct($value)
    {
        parent::__construct('locations.coordinates', $value);
    }

    /**
     * @param QueryBuilderInterface $queryBuilder
     * @return array|object
     */
    public function apply(QueryBuilderInterface $queryBuilder)
    {
        if (!$this->value instanceof GeocoderLocation) {
            return (object) [];
        }

        if (!$this->value->isValid()) {
            return (object) [];
        }

        return [
            'nested' => [
                'path' => $this->path(),
                'query' => [
                    'function_score' => [
                        'score_mode' => 'sum',
                        'functions' => [
                            [
                                'filter' => [
                                    'exists' => [
                                        'field' => $this->field
                                    ]
                                ],
                                'gauss' => [
                                    $this->field => [
                                        'origin' => [
                                            'lat' => $this->value->latitude,
                                            'lon' => $this->value->longitude
                                        ],
                                        'scale' => self::SCALE,
                                        'offset' => self::OFFSET
                                    ]
                                ],
                                'weight' => self::WEIGHT
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * @return string
     */
    private function path(): string
    {
        return explode('.', $this->field)[0];
    }
}
