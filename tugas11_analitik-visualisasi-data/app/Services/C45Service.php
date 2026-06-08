<?php

namespace App\Services;

class C45Service
{
    /**
     * Prediksi menggunakan Decision Tree (C4.5 / ID3)
     * 
     * @param \Illuminate\Database\Eloquent\Collection $trainingData
     * @param array $testData
     * @return array
     */
    public function predict($trainingData, $testData)
    {
        if ($trainingData->isEmpty()) {
            return ['prediction' => 'Tidak Diketahui', 'prob' => 0];
        }

        // 1. Discretization Data
        $discreteData = [];
        foreach ($trainingData as $data) {
            $discreteData[] = [
                'ipk' => $data->ipk >= 3 ? 'Tinggi' : 'Rendah',
                'kehadiran' => $data->kehadiran >= 80 ? 'Tinggi' : 'Rendah',
                'sks_lulus' => $data->sks_lulus >= 110 ? 'Tinggi' : 'Rendah',
                'status_kerja' => $data->status_kerja,
                'tepat_waktu' => $data->tepat_waktu
            ];
        }

        $discreteTest = [
            'ipk' => $testData['ipk'] >= 3 ? 'Tinggi' : 'Rendah',
            'kehadiran' => $testData['kehadiran'] >= 80 ? 'Tinggi' : 'Rendah',
            'sks_lulus' => $testData['sks_lulus'] >= 110 ? 'Tinggi' : 'Rendah',
            'status_kerja' => $testData['status_kerja']
        ];

        $attributes = ['ipk', 'kehadiran', 'sks_lulus', 'status_kerja'];
        
        // 2. Build Tree
        $tree = $this->buildTree($discreteData, $attributes);

        // 3. Traverse Tree to predict
        $prediction = $this->traverseTree($tree, $discreteTest);

        // Simple probability estimation based on how deep the tree goes or just 1.0 since it's a rule
        // A better prob could be the majority distribution at the leaf, but for simplicity we return 1.0
        return [
            'prediction' => $prediction,
            'prob' => 1.0, 
            'tree' => $tree // Optional: for debugging or showing the rules
        ];
    }

    private function buildTree($data, $attributes)
    {
        $targetValues = array_column($data, 'tepat_waktu');
        
        // Base case 1: If all target values are the same
        if (count(array_unique($targetValues)) === 1) {
            return ['type' => 'leaf', 'class' => $targetValues[0]];
        }
        
        // Base case 2: If no more attributes to split
        if (empty($attributes)) {
            $counts = array_count_values($targetValues);
            arsort($counts);
            return ['type' => 'leaf', 'class' => array_key_first($counts)];
        }

        $baseEntropy = $this->calculateEntropy($targetValues);

        $bestGain = -1;
        $bestAttribute = null;
        $bestSplits = [];

        foreach ($attributes as $attribute) {
            $splits = [];
            foreach ($data as $row) {
                $val = $row[$attribute];
                $splits[$val][] = $row;
            }

            $attributeEntropy = 0;
            foreach ($splits as $subset) {
                $prob = count($subset) / count($data);
                $subsetTargets = array_column($subset, 'tepat_waktu');
                $attributeEntropy += $prob * $this->calculateEntropy($subsetTargets);
            }

            $gain = $baseEntropy - $attributeEntropy;

            if ($gain > $bestGain) {
                $bestGain = $gain;
                $bestAttribute = $attribute;
                $bestSplits = $splits;
            }
        }

        // If we can't get any gain, return majority leaf
        if ($bestGain == 0 || $bestAttribute === null) {
            $counts = array_count_values($targetValues);
            arsort($counts);
            return ['type' => 'leaf', 'class' => array_key_first($counts)];
        }

        $node = [
            'type' => 'node',
            'attribute' => $bestAttribute,
            'branches' => []
        ];

        $remainingAttributes = array_values(array_diff($attributes, [$bestAttribute]));

        foreach ($bestSplits as $value => $subset) {
            $node['branches'][$value] = $this->buildTree($subset, $remainingAttributes);
        }

        // Catch-all branch for unseen data
        $counts = array_count_values($targetValues);
        arsort($counts);
        $node['default'] = array_key_first($counts);

        return $node;
    }

    private function calculateEntropy($targets)
    {
        $counts = array_count_values($targets);
        $total = count($targets);
        $entropy = 0;

        foreach ($counts as $count) {
            $prob = $count / $total;
            $entropy -= $prob * log($prob, 2);
        }

        return $entropy;
    }

    private function traverseTree($node, $data)
    {
        if ($node['type'] === 'leaf') {
            return $node['class'];
        }

        $attribute = $node['attribute'];
        $value = $data[$attribute];

        if (isset($node['branches'][$value])) {
            return $this->traverseTree($node['branches'][$value], $data);
        }

        return $node['default'];
    }
}
