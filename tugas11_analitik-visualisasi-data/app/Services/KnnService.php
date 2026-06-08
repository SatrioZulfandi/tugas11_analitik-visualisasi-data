<?php

namespace App\Services;

class KnnService
{
    /**
     * Prediksi menggunakan K-Nearest Neighbors
     * 
     * @param \Illuminate\Database\Eloquent\Collection $trainingData
     * @param array $testData
     * @param int $k
     * @return array
     */
    public function predict($trainingData, $testData, $k = 5)
    {
        if ($trainingData->isEmpty()) {
            return ['prediction' => 'Tidak Diketahui', 'prob' => 0];
        }

        // 1. Dapatkan nilai Min dan Max untuk normalisasi
        $minMax = $this->getMinMax($trainingData);

        // 2. Normalisasi Data Testing
        $testNormalized = [
            'ipk' => $this->normalize($testData['ipk'], $minMax['ipk']['min'], $minMax['ipk']['max']),
            'kehadiran' => $this->normalize($testData['kehadiran'], $minMax['kehadiran']['min'], $minMax['kehadiran']['max']),
            'sks_lulus' => $this->normalize($testData['sks_lulus'], $minMax['sks_lulus']['min'], $minMax['sks_lulus']['max']),
            'status_kerja' => $testData['status_kerja'] == 'Ya' ? 1 : 0
        ];

        // 3. Hitung jarak tiap data training
        $distances = [];
        foreach ($trainingData as $data) {
            $trainNormalized = [
                'ipk' => $this->normalize($data->ipk, $minMax['ipk']['min'], $minMax['ipk']['max']),
                'kehadiran' => $this->normalize($data->kehadiran, $minMax['kehadiran']['min'], $minMax['kehadiran']['max']),
                'sks_lulus' => $this->normalize($data->sks_lulus, $minMax['sks_lulus']['min'], $minMax['sks_lulus']['max']),
                'status_kerja' => $data->status_kerja == 'Ya' ? 1 : 0
            ];

            $distance = $this->euclideanDistance($testNormalized, $trainNormalized);
            
            $distances[] = [
                'distance' => $distance,
                'label' => $data->tepat_waktu
            ];
        }

        // 4. Urutkan berdasarkan jarak terdekat (ascending)
        usort($distances, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        // 5. Ambil K data teratas
        $topK = array_slice($distances, 0, $k);

        // 6. Majority Voting
        $votes = [
            'Ya' => 0,
            'Tidak' => 0
        ];

        foreach ($topK as $neighbor) {
            if (isset($votes[$neighbor['label']])) {
                $votes[$neighbor['label']]++;
            }
        }

        $prediction = $votes['Ya'] > $votes['Tidak'] ? 'Ya' : 'Tidak';
        $probability = $votes[$prediction] / $k;

        return [
            'prediction' => $prediction,
            'prob' => $probability,
            'votes_ya' => $votes['Ya'],
            'votes_tidak' => $votes['Tidak']
        ];
    }

    private function getMinMax($data)
    {
        return [
            'ipk' => [
                'min' => $data->min('ipk'),
                'max' => $data->max('ipk')
            ],
            'kehadiran' => [
                'min' => $data->min('kehadiran'),
                'max' => $data->max('kehadiran')
            ],
            'sks_lulus' => [
                'min' => $data->min('sks_lulus'),
                'max' => $data->max('sks_lulus')
            ]
        ];
    }

    private function normalize($value, $min, $max)
    {
        if ($max == $min) return 0;
        return ($value - $min) / ($max - $min);
    }

    private function euclideanDistance($p, $q)
    {
        $sum = 0;
        foreach ($p as $key => $value) {
            $sum += pow($value - $q[$key], 2);
        }
        return sqrt($sum);
    }
}
