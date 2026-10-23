# Endpoints usados (premissas)

| Operação | Requisição | Observação |
| --- | --- | --- |
| Login | `POST /login` com `login`, `senha`, `product`, `device` | Devolve um token Bearer, enviado em `Authorization: Bearer <token>` |
| Ler plano | `GET /configuracoes/planodehorarios` com header `search.name` | Resposta com a lista `sub_opcoes` |
| Gravar plano | `PATCH /configuracoes/planodehorarios` com header `search.name` e body `{"sub_opcoes": [...]}` | Sempre a lista completa |

Requisições de escrita enviam um header com a busca do registro (`search.<campo>`) e um body com os dados. Se um 401 voltar, o cliente faz login de novo uma vez e repete a requisição.

Estes formatos são premissas de um projeto de estudo. Consulte a documentação oficial da plataforma para o contrato real.
