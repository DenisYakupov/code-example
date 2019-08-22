<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 08.10.18
 * Time: 10:53
 */

namespace App\Module\FilterCore\FilterType\Resale;

use App\Module\FilterCore\FilterSqlContainer;
use App\Module\FilterCore\FilterType\AbstractFilterType;
use App\Module\FilterCore\FilterUrlBuilder;
use App\Providers\Resale\ResaleFilter\ResaleFilterProvider;
use App\Utils\Utils;

class LocationType extends AbstractFilterType
{

    const TYPE_COMPLEX       = 'complex';
    const TYPE_SUBWAY        = 'subway';
    const TYPE_REGION        = 'region';
    const TYPE_AREA          = 'area';
    const TYPE_CITY          = 'city';
    const TYPE_CITY_AREA     = 'city_area';
    const TYPE_CITY_DISTRICT = 'city_district';
    const TYPE_SETTLEMENT    = 'settlement';
    const TYPE_ADDRESS       = 'address';
    const TYPE_POLYGON       = 'polygon';

    private static $aliases = [
        self::TYPE_SUBWAY        => 's',
        self::TYPE_COMPLEX       => 'z',
        self::TYPE_REGION        => 'r',
        self::TYPE_AREA          => 'a',
        self::TYPE_CITY          => 'c',
        self::TYPE_CITY_AREA     => 'o',
        self::TYPE_CITY_DISTRICT => 'd',
        self::TYPE_SETTLEMENT    => 't',
        self::TYPE_ADDRESS       => 'e',
    ];

    private static $priorities = [
        self::TYPE_REGION        => 200,
        self::TYPE_AREA          => 199,
        self::TYPE_CITY          => 198,
        self::TYPE_CITY_AREA     => 197,
        self::TYPE_CITY_DISTRICT => 196,
        self::TYPE_SETTLEMENT    => 195,
        self::TYPE_SUBWAY        => 194,
        self::TYPE_COMPLEX       => 193,
        self::TYPE_ADDRESS       => 192,

    ];

    private static $parseUrlTypes = [
        self::TYPE_COMPLEX,
        self::TYPE_SUBWAY,
        self::TYPE_REGION,
        self::TYPE_AREA,
        self::TYPE_CITY,
        self::TYPE_CITY_AREA,
        self::TYPE_CITY_DISTRICT,
        self::TYPE_SETTLEMENT,
        self::TYPE_ADDRESS,
//        self::TYPE_POLYGON,
    ];


    public function getCode(): string
    {
        return 'location';
    }

    public function getTemplateParameters() :array
    {
        return [];
    }

    /**
     * @param string $url
     * @return array
     */
    public function getUrlParts(string $url): array
    {
        $return = [];
        $tpl = '/\/(%s\-[a-z0-9\-]+)/';
        $types = [];
        $requiredTypes = [
            self::TYPE_SUBWAY,
            self::TYPE_COMPLEX,
            self::TYPE_REGION,
            self::TYPE_AREA,
            self::TYPE_CITY,
            self::TYPE_CITY_AREA,
            self::TYPE_CITY_DISTRICT,
            self::TYPE_SETTLEMENT,
//            self::TYPE_ADDRESS,
        ];
        foreach ($requiredTypes as $typeCode) {
            $types[] = [
                'alias' => self::$aliases[$typeCode],
                'data' => array_keys($this->getProvidersData()[$typeCode]),
            ];
        }

        foreach ($types as $type) {
            preg_match(sprintf($tpl, $type['alias']), $url, $matches);
            if (empty($matches)) {
                continue;
            }
            $value = preg_replace("/{$type['alias']}-/", '', $matches[1], 1);
            $return[] = in_array($value, $type['data']) ? $matches[1] : null;
        }

        $return = array_filter($return, function ($item) {
            return is_null($item) ? false : true;
        });
        return $return;
    }

    public function getAvailableValues(): array
    {
        $locations = $this->getFilterBuilder()->getData()[$this->getCode()] ?? [];

        $types = array_column($locations, 'type');
        $values = [];

        $requiredTypes = [
            self::TYPE_COMPLEX,
            self::TYPE_SUBWAY,
            self::TYPE_REGION,
            self::TYPE_AREA,
            self::TYPE_CITY,
            self::TYPE_CITY_AREA,
            self::TYPE_CITY_DISTRICT,
            self::TYPE_SETTLEMENT,
//            self::TYPE_ADDRESS,
        ];
        foreach ($requiredTypes as $requiredType) {
            if (!in_array($requiredType, $types)) {
                $values = array_merge($values, array_values($this->getProvidersData()[$requiredType]));
            }
        }

        return array_map(function ($item) { return [$item]; }, $values);
    }

    public function transformValueFromUrl(string $url)
    {
        $params = $this->parseUrl($url);
        if(!is_array($params) || empty($params)) {
            return $this->parseQuery($url);
        }

        $queryParams = $this->parseQuery($url);
        if(!is_array($queryParams) || empty($queryParams)) {
            return $params;
        }

        foreach ($queryParams as $item) {
            $found = false;
            foreach($params as $param) {
                if($param['type'] === $item['type'] && $param['value'] === $item['value']) {
                    $found = true;
                }
            }
            if(!$found) {
                $params[] = $item;
            }
        }

        return $params;
    }

    private function parseUrl($url) :?array
    {
        $urlTemplate = '/\/%s\-([a-z0-9-]+)/';
        $filters = [];
        foreach (self::$parseUrlTypes as $type) {
            preg_match(sprintf($urlTemplate, self::$aliases[$type] ?? $type), $url, $matches);
            $val = !empty($matches) ? [$matches[1]] : null;
            if (is_null($val)) {
                $filters[$type] = null;
            } else {
                $filters[$type] = $val;
            }

        }

        $filters = array_filter($filters, function ($item) {
            if (is_null($item)) {
                return false;
            }
            return true;
        });

        return $this->getFilterBuilder()->locationTransform($filters);
    }

    private function parseQuery($url) :?array
    {
        $queryStr = parse_url($url)['query'] ?? '';
        parse_str($queryStr, $params);

        $filters = [];
        foreach (self::$parseUrlTypes as $parseType) {
            $filters[$parseType] = $params[$parseType] ?? null;
        }

        // пакуем и удаляем если значение null
        $filters = array_filter($filters, function ($item) {
            if (is_null($item)) {
                return false;
            }
            return true;
        });

        return $this->getFilterBuilder()->locationTransform($filters);
    }

    public function transformSqlFormValue(FilterSqlContainer $filterSqlContainer, $value) :void
    {
        $locations = $value ? $value : [];
        foreach ($locations as $location) {//todo отрефакторить
            if (isset($location['value']) && !empty($location['value'])) {
                switch ($location['type']) {
                    case self::TYPE_COMPLEX:
                        $key = 'complex_slug';
                        $params = $filterSqlContainer->get($key) ?? [];
                        $filterSqlContainer->set($key, array_merge($params, [$location['value']]));
                        break;
                    case self::TYPE_SUBWAY:
                        $key = 'subway_title';
                        $params = $filterSqlContainer->get($key) ?? [];
                        $filterSqlContainer->set($key, array_merge($params, [$location['value']]));
                        break;

                    case self::TYPE_REGION:
                        $key = 'dadata_region';
                        $params = $filterSqlContainer->get($key) ?? [];
                        $value = $location['value'];
                        if ($value === ResaleFilterProvider::REGION_MOSCOW_REGION_FULL) {
                            $value = ResaleFilterProvider::switchMoscowRegion($value);
                        }
                        $filterSqlContainer->set($key, array_merge($params, [$value]));
                        break;
                    case self::TYPE_AREA:
                        $key = 'dadata_area';
                        $params = $filterSqlContainer->get($key) ?? [];
                        $filterSqlContainer->set($key, array_merge($params, [$location['value']]));
                        break;
                    case self::TYPE_CITY:
                        $key = 'dadata_city';
                        $params = $filterSqlContainer->get($key) ?? [];
                        $filterSqlContainer->set($key, array_merge($params, [$location['value']]));
                        break;
                    case self::TYPE_CITY_AREA:
                        $key = 'dadata_city_area';
                        $params = $filterSqlContainer->get($key) ?? [];
                        $filterSqlContainer->set($key, array_merge($params, [$location['value']]));
                        break;
                    case self::TYPE_CITY_DISTRICT:
                        $key = 'dadata_city_district';
                        $params = $filterSqlContainer->get($key) ?? [];
                        $filterSqlContainer->set($key, array_merge($params, [$location['value']]));
                        break;
                    case self::TYPE_SETTLEMENT:
                        $key = 'dadata_settlement';
                        $params = $filterSqlContainer->get($key) ?? [];
                        $filterSqlContainer->set($key, array_merge($params, [$location['value']]));
                        break;
                    case self::TYPE_ADDRESS:
                        $key = 'dadata_result';
                        $params = $filterSqlContainer->get($key) ?? [];
                        $filterSqlContainer->set($key, array_merge($params, [$location['value']]));
                        break;
                }
            }
        }
    }

    public function transformUrlFormValue(FilterUrlBuilder $urlBuilder, $value) : void
    {
        $locations = $value ? $value : [];

        $exclude = [self::TYPE_ADDRESS]; // исключаем из урла, кидаем в query

        $types = [];
        foreach ($locations as $location) {
            switch ($location['type']) {
                case self::TYPE_COMPLEX:
                    $types[self::TYPE_COMPLEX][] = $location['meta']['text'] ?? $location['value'];
                    break;
                case self::TYPE_SUBWAY:
                    $countType = array_count_values(array_column($locations,'type'));
                    if (isset($countType[self::TYPE_SUBWAY]) && $countType[self::TYPE_SUBWAY] > 1) {
                        $types[self::TYPE_SUBWAY][] = $location['meta']['subway_id'];
                        break;
                    }
                    $types[self::TYPE_SUBWAY][] = $location['value'];
                    break;
                case self::TYPE_REGION:
                    $types[self::TYPE_REGION][] = $location['value'];
                    break;
                case self::TYPE_AREA:
                    $types[self::TYPE_AREA][] = $location['value'];
                    break;
                case self::TYPE_CITY:
                    $types[self::TYPE_CITY][] = $location['value'];
                    break;
                case self::TYPE_CITY_AREA:
                    $types[self::TYPE_CITY_AREA][] = $location['value'];
                    break;
                case self::TYPE_CITY_DISTRICT:
                    $types[self::TYPE_CITY_DISTRICT][] = $location['value'];
                    break;
                case self::TYPE_SETTLEMENT:
                    $types[self::TYPE_SETTLEMENT][] = $location['value'];
                    break;
                case self::TYPE_ADDRESS:
                    $types[self::TYPE_ADDRESS][] = $location['value'];
                    break;
            }
        }

        foreach ($types as $key => $value) {
            if (is_array($value) && count($value) === 1) {
                //подбираем алиас
                $attribute = sprintf('%s-%s', self::$aliases[$key], Utils::slugify($value[0]));
                $breadcrumb = '';
                switch ($key) {
                    case self::TYPE_COMPLEX:
                        $breadcrumb = sprintf('%s', $value[0]);
                        break;
                    case self::TYPE_SUBWAY:
                        $breadcrumb = sprintf('м. %s', $value[0]);
                        break;
                    case self::TYPE_REGION:
                        $breadcrumb = sprintf('%s', $value[0]);
                        break;
                    case self::TYPE_AREA:
                        $breadcrumb = sprintf('%s', $value[0]);
                        break;
                    case self::TYPE_CITY:
                        $breadcrumb = sprintf('%s', $value[0]);
                        break;
                    case self::TYPE_CITY_AREA:
                        $breadcrumb = sprintf('%s', $value[0]);
                        break;
                    case self::TYPE_CITY_DISTRICT:
                        $breadcrumb = sprintf('%s', $value[0]);
                        break;
                    case self::TYPE_SETTLEMENT:
                        $breadcrumb = sprintf('%s', $value[0]);
                        break;
                    /*case self::TYPE_ADDRESS:
                        $breadcrumb = sprintf('%s', $value[0]);
                        break;*/
                }

                if (!in_array($key, $exclude)) {
                    $urlBuilder->addAttribute($attribute, self::$priorities[$key], $breadcrumb);
                } else {
                    $urlBuilder->addQuery($key, $value);
                }
            } else {
                $urlBuilder->addQuery($key, $value);
            }
        }
    }

    public function findGroupedDataForAvailableValues(array $groupedData, $value) :bool
    {
        $data = $groupedData[$value[0]['type']] ?? [];
        if (in_array($value[0]['value'], $data)) {
            return true;
        }

        return false;
    }

    /** Получить значение с наименьшим приоритетом
     * @return array|null
     */
    public function getLowestPriorityValue() :?array
    {
        $data = $this->getFilterBuilder()->getData()[$this->getCode()];

        $lowestValue = null;
        $lowestPriority = PHP_INT_MAX;
        foreach ($data as $item) {
            $itemPriority = self::$priorities[$item['type']];
            if ($itemPriority <= $lowestPriority) {
                $lowestPriority = $itemPriority;
                $lowestValue = [$item];
            }
        }

        return $lowestValue;
    }


    private function getProvidersData()
    {
        return $this->getFilterBuilder()->getProvidersData();
    }

}