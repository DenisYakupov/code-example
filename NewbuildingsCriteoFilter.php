<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 07.11.18
 * Time: 17:40
 */

namespace App\Service\Export\Filters;


use App\Entity\Complex;
use App\Repository\ComplexRepository;
use App\Service\Export\AbstractFilter;
use App\Service\Export\ExportFormatInterface;
use Doctrine\ORM\EntityManagerInterface;

class NewbuildingsCriteoFilter extends AbstractFilter
{

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager);
    }

    public function getEntity(): string
    {
        return ExportFormatInterface::ENTITY_COMPLEX;
    }

    public function getData(): array
    {
        /* Фильтр по guid */
//        E5941AF8-6845-11E6-A5DE-5404A6F33119 -- Мкр. «Красногорский»
//        E5DC3CD4-6845-11E6-A5DE-5404A6F33119 -- ЖК «Город»
//        E5CAA262-6845-11E6-A5DE-5404A6F33119 -- ЖК «Мытищи Lite»
//        31C07BB2-43E4-11E8-A56C-5404A6F33119 -- Квартал «Парк Легенд»
//        E5DEC03A-6845-11E6-A5DE-5404A6F33119 -- ЖК «Андреевка»
//        E5970D4E-6845-11E6-A5DE-5404A6F33119 -- ЖК «Ленинградский»
//        F810FB44-8C9C-11E6-B0D4-5404A6F33119 -- Жилой дом «Дуэт»
//        E5958E38-6845-11E6-A5DE-5404A6F33119 -- ЖК «Планерный»

        $filter = [
            'guid' => [
                "E5941AF8-6845-11E6-A5DE-5404A6F33119",
                "E5DC3CD4-6845-11E6-A5DE-5404A6F33119",
                "E5CAA262-6845-11E6-A5DE-5404A6F33119",
                "31C07BB2-43E4-11E8-A56C-5404A6F33119",
                "E5DEC03A-6845-11E6-A5DE-5404A6F33119",
                "E5970D4E-6845-11E6-A5DE-5404A6F33119",
                "F810FB44-8C9C-11E6-B0D4-5404A6F33119",
                "E5958E38-6845-11E6-A5DE-5404A6F33119",
                "BB5108FA-4416-11E9-A8A0-5404A6F33119" //Сказочный лес
            ]
        ];

        /** @var ComplexRepository $comRep */
        $comRep = $this->entityManager->getRepository(Complex::class);
        $data = $comRep->getList(0, self::LIMIT, null, null, $filter);

        usort($data, function ($a, $b) {
            if ($a['id'] == $b['id']) {
                return 0;
            }
            return $a['id'] < $b['id'] ? -1 : 1;
        });

        $i = 0;
        foreach ($data as $item) {
            if (isset($item['distance']) && !empty($item['distance'])) {
                $distance = json_decode($item['distance'], true);
                $data[$i]['distanceCriteo'] = reset($distance);
            } else {
                $data[$i]['distanceCriteo'] = null;
            }
            $i++;
        }

        return $data;
    }

    public function getAlias(): array
    {
        return ['newbuildings-criteo'];
    }
}
