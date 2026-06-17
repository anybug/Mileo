<?php

namespace App\Service;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class ChartService
{
    public function __construct(
        private readonly ChartBuilderInterface $chartBuilder,
    )
    {}

    public function createDataChart
    (
        string $type,
        array $labels,
        array $data,
        string $label = '',
    ): Chart {

        $chart = $this->chartBuilder->createChart($type);

        $colors = [
            '#5368d5', '#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f',
            '#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#bab0ab',
            '#8cd17d', '#bd7ebe', '#fabfd2', '#b6992d', '#e9967a'
        ];

        $bgColors = [
            Chart::TYPE_PIE => $colors,
            Chart::TYPE_LINE => 'rgba(13,110,253,0.15)',
            Chart::TYPE_BAR => $colors[0],
        ];

        //const backgroundColors = (type === 'pie') ? generateColors(labels.length) : 'rgba(54, 162, 235, 0.6)';
        //const borderColors = (type === 'pie') ? backgroundColors : 'rgba(54, 162, 235, 1)';

        $options = [
                    'responsive' => true,
                    'maintainAspectRatio' => true,
                    'plugins' => [
                        'legend' => ['position' => 'bottom'],
                        'tooltip' => ['enabled' => true]
                    ]
                ];
        
        $chart->setData([
            'labels' => array_map(function($val) {return $this->truncateLabel($val, 30);}, $labels),
            'datasets' => [[
                'label' => $label, 0, 30,
                'data' => $data,
                'borderColor' => $type == Chart::TYPE_LINE ? $colors[0] : '#ffffff',
                'backgroundColor' => $bgColors[$chart->getType()],
                'fill' => $type == Chart::TYPE_LINE ? true : false,
                'tension' => 0.3,
            ]]
        ]);

        $chart->setOptions($options);

        return $chart;
    }

    public function truncateLabel(string $string, int $length = 20, string $suffix = '...'): string
    {
        if (mb_strlen($string, 'UTF-8') <= $length) {
            return $string;
        }

        return mb_substr($string, 0, $length, 'UTF-8') . $suffix;
    }


}