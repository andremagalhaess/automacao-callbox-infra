# Plano de Horários: regras da automação

Cada atendimento tem um Plano de Horários. A ligação percorre as prioridades em ordem até encaixar em uma; se não encaixar em nenhuma, vai para o transbordo.

## Exemplo (dados fictícios)

| Prioridade | Tipo | Mês | Dia | Dia da semana | Horário | Destino | Áudio |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | Pausa de feriado | Setembro | 7 | Segunda | 00:00–23:59 | Desligar | pausa_feriado_0709 |
| 2 | Pausa de treinamento | Setembro | 4 | Sexta | 16:45–17:59 | Desligar | pausa_treinamento_0409 |
| 4 | Funcionamento (protegida) | Todos | Todos | Segunda a sexta | 08:00–17:59 | Atendimento A | n/a |
| Transbordo | Fora do horário | n/a | n/a | n/a | n/a | Atendimento de transbordo | n/a |

## Regras

1. Pausas usam prioridades menores que a de funcionamento, para serem avaliadas antes dela.
2. A prioridade de funcionamento (a 4 no exemplo) nunca é editada nem apagada.
3. Cada pausa tem data, faixa de horário, destino Desligar e um áudio próprio.
4. Pausas vencidas podem ser removidas depois da data, sem tocar nas outras prioridades.
5. O número da prioridade de funcionamento varia entre atendimentos: deve ser lido do plano antes de gravar.

## Risco de sobrescrita

O `PATCH` do plano envia a lista `sub_opcoes`. Se a API substituir a lista inteira, enviar uma pausa sozinha apagaria a prioridade de funcionamento. Antes de usar em produção:

1. Testar em homologação se o `PATCH` substitui ou mescla.
2. Sempre ler o plano atual, preservar as prioridades existentes e reenviar tudo.
