# AIEditingAssistant

## Basic configuration

- Configure active provider (one of options in `AIEditingAssistantProviders` attribute )

```php
$wgAIEditingAssistantActiveProvider = 'open-ai';
```

- Configure connection params for selected provider, in case of OpenAI: 

```php
$wgAIEditingAssistantActiveProviderConnection = [
         'secret' => '...'
 ];
```