<?php

declare(strict_types=1);

namespace CallboxAutomation\Api;

use CallboxAutomation\Exception\CallboxException;

/**
 * Leitura e gravação das prioridades (sub_opcoes) de um Plano de Horários.
 * Os nomes de campos e o formato da resposta ficam concentrados aqui: se a API real
 * for diferente do assumido, só esta classe e o PauseTemplate precisam mudar.
 */
final class ScheduleApi
{
    private const PATH = '/configuracoes/planodehorarios';

    public function __construct(private readonly CallboxClient $client)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function getEntries(string $planName): array
    {
        $response = $this->client->request('GET', self::PATH, ['name' => $planName]);
        if (!$response->ok()) {
            throw new CallboxException("Não consegui ler o plano '{$planName}' (HTTP {$response->status}).");
        }

        $entries = $response->data['sub_opcoes'] ?? null;
        if (!is_array($entries)) {
            throw new CallboxException("Resposta sem 'sub_opcoes' para o plano '{$planName}'.");
        }

        return array_values($entries);
    }

    /**
     * Envia a lista COMPLETA. Se a API substituir a lista inteira (a ser validado em homologação),
     * mandar só as pausas apagaria as demais prioridades.
     *
     * @param array<int,array<string,mixed>> $entries
     */
    public function saveEntries(string $planName, array $entries): void
    {
        $response = $this->client->request('PATCH', self::PATH, ['name' => $planName], [
            'sub_opcoes' => array_values($entries),
        ]);
        if (!$response->ok()) {
            throw new CallboxException("Não consegui gravar o plano '{$planName}' (HTTP {$response->status}).");
        }
    }
}
