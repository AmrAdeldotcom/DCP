<?php
declare(strict_types=1);
interface ProviderAdapter {
 public function models(array $provider):array;
 public function chat(array $provider,string $model,array $messages):string;
}
