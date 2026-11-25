<?php
declare(strict_types=1);
interface ToolCallingProviderAdapter extends ProviderAdapter {
 /** @param list<array<string,mixed>> $messages @param list<array<string,mixed>> $tools */
 public function chatWithTools(array $provider,string $model,array $messages,array $tools):ProviderToolCallResult;
}
