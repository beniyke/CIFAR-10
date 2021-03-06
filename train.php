<?php

include __DIR__ . '/vendor/autoload.php';

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Pipeline;
use Rubix\ML\Transformers\ImageResizer;
use Rubix\ML\Transformers\ImageVectorizer;
use Rubix\ML\Transformers\ZScaleStandardizer;
use Rubix\ML\Classifiers\MultiLayerPerceptron;
use Rubix\ML\NeuralNet\Layers\Dense;
use Rubix\ML\NeuralNet\Layers\Activation;
use Rubix\ML\NeuralNet\Layers\BatchNorm;
use Rubix\ML\NeuralNet\ActivationFunctions\ELU;
use Rubix\ML\NeuralNet\Optimizers\Adam;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Other\Loggers\Screen;
use League\Csv\Writer;

use function Rubix\ML\array_transpose;

ini_set('memory_limit', '-1');

echo 'Loading data into memory ...' . PHP_EOL;

$samples = $labels = [];

foreach (glob('train/*.png') as $file) {
    $samples[] = [imagecreatefrompng($file)];
    $labels[] = preg_replace('/[0-9]+_(.*).png/', '$1', basename($file));
}

$dataset = new Labeled($samples, $labels);

$estimator = new PersistentModel(
    new Pipeline([
        new ImageResizer(32, 32),
        new ImageVectorizer(),
        new ZScaleStandardizer(),
    ], new MultiLayerPerceptron([
        new Dense(200),
        new Activation(new ELU()),
        new Dense(200),
        new BatchNorm(),
        new Activation(new ELU()),
        new Dense(150),
        new Activation(new ELU()),
        new Dense(100),
        new BatchNorm(),
        new Activation(new ELU()),
        new Dense(50),
        new Activation(new ELU()),
    ], 100, new Adam(0.001))),
    new Filesystem('cifar-10.model', true)
);

$estimator->setLogger(new Screen('CIFAR10'));

echo 'Training ...' . PHP_EOL;

$estimator->train($dataset);

$scores = $estimator->scores();
$losses = $estimator->steps();

$writer = Writer::createFromPath('progress.csv', 'w+');
$writer->insertOne(['score', 'loss']);
$writer->insertAll(array_transpose([$scores, $losses]));

echo 'Progress saved to progress.csv' . PHP_EOL;

if (strtolower(trim(readline('Save this model? (y|[n]): '))) === 'y') {
    $estimator->save();
}
