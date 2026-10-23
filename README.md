# Callbox Automation

Ferramenta em PHP para aplicar pausas e feriados em vários Planos de Horários do Callbox de uma vez, usando a API REST da plataforma, com prévia (dry-run), log por plano e reversão.

> Versão de estudo, escrita do zero com dados fictícios. Ela reimplementa uma ideia que desenvolvi em contexto profissional, sem código, nomes ou dados de nenhuma empresa. Os nomes de campos e o formato das respostas da API são **assumidos** e ficam concentrados em `ScheduleApi` e `PauseTemplate`, para serem ajustados à API real.

## O problema

Em um plano de horários, a ligação percorre as prioridades em ordem até encaixar em uma; se não encaixar em nenhuma, vai para o transbordo. Configurar um feriado exige editar cada plano à mão, e um erro pode apagar a prioridade de funcionamento. A ferramenta faz isso em lote e de forma segura.

## Regras que o código garante (e os testes provam)

- Pausas usam prioridades menores que a de funcionamento, para serem avaliadas antes dela.
- A prioridade de funcionamento nunca é editada nem apagada; o número dela pode variar entre planos e é lido do próprio plano.
- Sempre lê o plano atual, preserva as entradas existentes (inclusive campos desconhecidos) e reenvia a lista completa.
- Aplicar o mesmo modelo duas vezes não duplica a pausa.
- Sem prioridade livre ou sem funcionamento válido, falha em vez de renumerar ou chutar.
- Um plano com erro não interrompe os demais; tudo fica no relatório.
- Credenciais só no `.env`, nunca em mensagens de erro nem no repositório.

## Segurança em lote

- **Prévia por padrão.** Para gravar são necessários dois comandos explícitos: `CALLBOX_DRY_RUN=false` no `.env` e a opção `--aplicar`.
- **Limite por execução** (`CALLBOX_BATCH_LIMIT`).
- **Log por plano** em `logs/` (JSON Lines) com o estado antes e depois.
- **Reversão** de uma execução inteira restaurando o estado anterior de cada plano.

## Estrutura

```
bin/callbox-pausas.php   linha de comando
src/Api/                 CallboxClient (login, token Bearer, renovação em 401) e ScheduleApi
src/Domain/              PausePlanner, PauseTemplate, PlanChange (lógica pura, sem rede)
src/Service/             PauseBatchService (lote, dry-run, log, reversão)
src/Http/                interface HttpClient e implementação com cURL
src/Support/             BatchLog, BatchReport, EnvFile
tests/                   21 testes, com servidor falso em memória
examples/                modelo de pausa e lista de planos fictícios
docs/                    regras do plano e limitações
```

## Como usar

```bash
cp .env.example .env     # preencha as credenciais
php bin/callbox-pausas.php adicionar --planos=examples/planos.exemplo.json --modelo=examples/feriado.exemplo.json
php bin/callbox-pausas.php adicionar --planos=examples/planos.exemplo.json --modelo=examples/feriado.exemplo.json --aplicar
php bin/callbox-pausas.php limpar-vencidas --planos=examples/planos.exemplo.json
php bin/callbox-pausas.php reverter --execucao=<ID mostrado no relatório>
```

## Testes

```bash
php tests/run.php        # sem dependências; também: composer test
```

O servidor falso dos testes **substitui a lista inteira** no PATCH, o pior caso possível, para provar que o código nunca perde a prioridade de funcionamento.

## Tecnologias

PHP 8.2+, cURL, API REST, arquitetura em camadas (domínio puro, serviço, infraestrutura HTTP injetável), testes automatizados.

## Limitações

Veja [`docs/limitacoes.md`](docs/limitacoes.md). O estudo de viabilidade que antecedeu a automação está em [`docs/estudo-de-viabilidade.md`](docs/estudo-de-viabilidade.md).
