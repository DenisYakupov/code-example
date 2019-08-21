<?php

namespace App\Providers\Complex\Card;

use App\Providers\AbstractProvider;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Получение комплекса новостроек
 *
 * @package App\Providers\Complex
 */
class ComplexCardProvider extends AbstractProvider
{
    private $sqlParameters;

    /**
     * Получение комплекса
     *
     * @return array
     */
    protected function buildResult() : array
    {
        $sql = $this->buildSql();
        $item = $this->fetchOne($sql, $this->sqlParameters);

        if (!$item) {
            throw new NotFoundHttpException();
        }

        // преобразователи
        $item['slider_content'] = $this->prepareSliderContent($item);
        $item['subway'] = $this->prepareSubway($item);
        $item['adv'] = $this->prepareAdv($item);
        $item['cameras'] = $this->prepareCameras($item);
        $item['tours'] = $this->prepareTours($item);
        $item['genplan'] = $this->prepareGenplan($item);

        return $item;
    }

    /**
     * @return array
     */
    private function getSortTypes()
    {
        return ['id', 'minPrice', 'minMeterPrice', 'rand()', 'c.priority'];
    }

    /**
     * @return string
     */
    private function buildSql()
    {
        // явное получение всех параметров в одном месте, без значений по умолчанию должно упростить контроль поведения выборки
        $sortType = $this->getParameter('sortType');
        $filters = $this->getParameter('filters');

        $sql = "
        SELECT
          c.id,
          gal.file_list as slider_content,
          c.title,
          c.mortgage_subsidized_rate,
          c.mortgage_min_rate,
          c.default_show_filter_param as def_show,
          c.description,
          c.slug,
          c.special_offers,
          c.guid as guid,
          IFNULL(min(CASE
                     WHEN f.min_price > 0 AND f.status != 'resale' AND f.min_price < (f.meter_price * f.area) THEN f.min_price
                     ELSE f.area * f.meter_price
                     END), 0) AS minPrice,
          IFNULL(min(CASE
                     WHEN f.min_price > 0 AND f.status != 'resale' AND f.meter_price > (f.min_price / f.area) THEN f.min_price / f.area
                     ELSE meter_price
                     END), 0) AS meterMinPrice,
          CONCAT_WS(
              '',
              '[{',
              group_concat(DISTINCT
                           '\"subwayId\":\"', sbst.id, '\",',
                           '\"subwayName\":\"', sbst.title, '\",',
                           '\"lineColor\":\"', sbln.color, '\",',
                           '\"distTime\":\"', dis.time, '\",',
                           '\"distTransport\":\"', dis.transport, '\"'
                           SEPARATOR '},{'
              ),
              '}]'
          ) AS distance,
          CONCAT_WS(
              '',
              '[{',
              group_concat(DISTINCT
                           '\"adId\":\"', ad.id, '\",',
                           '\"type\":\"', ad.type, '\",',
                           '\"title_short\":\"', ad.title_short, '\"'
                           SEPARATOR '},{'
              ),
              '}]'
          ) AS adv,
          c.address_city,
          c.address_street,
          c.address_house,
          c.address_region,
          CONCAT_WS(
            '', 
            '[{',
            GROUP_CONCAT(DISTINCT
                  '\"id\":\"', cam.id, '\",', 
                  '\"state\":\"', cam.state, '\",', 
                  '\"name\":\"', REPLACE(REPLACE(cam.name, '\\\', ''), '\"', '\\\\\"'), '\",', 
                  '\"width\":\"', cam.width, '\",'
                  '\"height\":\"', cam.height, '\"'
                  SEPARATOR '},{'
            ), 
            '}]' 
        ) AS cameras,
        CONCAT_WS(
            '', 
            '[{',
            GROUP_CONCAT(DISTINCT
                  '\"id\":\"', tours.id, '\",', 
                  '\"state\":\"', tours.state, '\",', 
                  '\"name\":\"', REPLACE(REPLACE(tours.name, '\\\', ''), '\"', '\\\\\"'), '\",', 
                  '\"width\":\"', tours.width, '\",'
                  '\"stream_url\":\"', tours.stream_url, '\",'
                  '\"height\":\"', tours.height, '\"'
                  SEPARATOR '},{'
            ), 
            '}]' 
        ) AS tours,
        c.genplan as genplan
        FROM complex c
          LEFT JOIN housing h
            ON c.id = h.complex_id
               AND h.show_on_site = 1
               AND h.housing_object_type='flats'
          LEFT JOIN flat f
            ON f.housing_id = h.id
               AND f.status IN ('free', 'resale')
               AND (f.meter_price > 0 OR f.min_price > 0)
               AND f.area > 0
               AND h.show_on_site = 1
          LEFT JOIN complex_distances comdis
            ON comdis.complex_id = c.id
          LEFT JOIN distance dis
            ON dis.id = comdis.distance_id
          LEFT JOIN subway_station sbst
            ON sbst.id = dis.subway_station_id
          LEFT JOIN subway_line sbln
            ON sbln.id = sbst.subway_line_id
          LEFT JOIN gallery as gal
            ON c.glide_id = gal.id
          LEFT JOIN ad_campaign ad
            ON c.id = ad.complex_id
               AND NOW() BETWEEN ad.start and ad.end
          LEFT JOIN camera cam 
            ON cam.complex_id = c.id AND cam.is_virtual_tour = 0 and cam.state in ('online','maintenance')
          LEFT JOIN camera tours
            ON tours.complex_id = c.id AND tours.is_virtual_tour = 1 and tours.state in ('online','maintenance')
        WHERE c.show_on_site = 1
        ";

        //where фильтры
        $preparedFilters = $this->buildFilters($filters);
        $sql .= " {$preparedFilters['where']} ";

        $sql .= " GROUP BY c.id ";

        // сортировка
        if (in_array($sortType, $this->getSortTypes())) {
            $sql .= " ORDER BY $sortType ";
        } else {
            throw new InvalidArgumentException(sprintf('Сортировка по полю(%s) недоступна', $sortType));
        }

        $sql .= " LIMIT 1 OFFSET 0 ";

        $this->sqlParameters = $preparedFilters['params'];
        return $sql;
    }

    /**
     * @param $filters
     * @return array
     */
    private function buildFilters($filters)
    {
        $where = [];
        $params = [];

        // фильтры начало
        if (isset($filters['slug'])) {
            $where[] = "c.slug = :slug";
            $params['slug'] = $filters['slug'];
        }
        if (isset($filters['exclude_id'])) {
            $where[] = "c.id <> :exclude_id";
            $params['exclude_id'] = $filters['exclude_id'];
        }

        // фильтры конец

        $where = implode(' AND ', $where);
        $where = empty($where) ? '' : ' AND ' . $where;
        return compact('where', 'params');
    }


    /**
     * @param $item
     */
    private function prepareAdv($item)
    {
        // перегоним поля json_array в массив
        if (mb_strlen($item['adv']) <= 4) { // если в строке что то вроде [{}]
            return [];
        } else {
            return json_decode($item['adv'], true);
        }
    }


    /**
     * @param $item
     * @return array
     */
    private function prepareCameras($item):array
    {
        if(!isset($item['cameras']) || mb_strlen($item['cameras']) <= 4) {
            return [];
        }

        return ComplexCardProvider::convertFromJson($item['cameras']);
    }


    /**
     * @param $item
     * @return array
     */
    private function prepareTours($item): array
    {
        if(!isset($item['tours']) || mb_strlen($item['tours']) <= 4) {
            return [];
        }

        return ComplexCardProvider::convertFromJson($item['tours']);
    }


    /**
     * @param $item
     * @return array
     */
    private function prepareGenplan($item): array
    {
        if(!isset($item['genplan']) || mb_strlen($item['genplan']) <= 4) {
            return [];
        }

        return ComplexCardProvider::convertFromJson($item['genplan']);
    }

    /**
     * @param $item
     * @return
     */
    private function prepareSliderContent($item)
    {
        if (isset($item['slider_content']) && !empty($item['slider_content'])) {
            $images = json_decode($item['slider_content'], true);
            usort($images, function ($a, $b) {
                if ($a['sort'] == $b['sort']) {
                    return 0;
                }
                return ($a['sort'] < $b['sort']) ? -1 : 1;
            });
            return $images;
        } else {
            return null;
        }
    }

    /**
     * @param $item
     * @return
     */
    private function prepareSubway($item)
    {
        $subwayData = json_decode($item['distance'], true);
        return (isset($subwayData[0]) && !empty($subwayData[0]) ? $subwayData : null);
    }

    private static function convertFromJson($string): array
    {
        $converted = json_decode($string, true);
        if(JSON_ERROR_NONE === json_last_error()) {
            return $converted;
        }
        return [];
    }
}