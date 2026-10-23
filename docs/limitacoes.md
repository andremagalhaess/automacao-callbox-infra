# Limitações e premissas

- **Contrato da API assumido.** Este projeto não foi testado contra uma API real. Os nomes de campos (`prioridade`, `tipo`, `mes`, `dia`, `dia_semana`, `horario`, `opcao`, `audio`), o formato `sub_opcoes` e o campo `token` do login são premissas. Para adaptar, ajuste `ScheduleApi` e `PauseTemplate`.
- **PATCH que substitui a lista.** O código assume o pior caso e sempre envia a lista completa. Se a API real mesclar em vez de substituir, o comportamento continua seguro, só redundante. Vale validar em homologação.
- **Pausas vencidas.** O plano não guarda o ano. `limpar-vencidas` remove apenas pausas com mês e dia numéricos que já passaram neste ano, e nunca toca no funcionamento nem em outros tipos de entrada.
- **Reversão restaura um snapshot.** Ela devolve o plano ao estado de antes da execução e sobrescreve edições feitas depois dela.
- **Logs locais.** `logs/` guarda o estado dos planos e não deve ser versionado (já está no `.gitignore`).
- **Fora do escopo.** Templates de áudio por TTS, cadastro de ramais e agentes e dashboard de diagnóstico.
