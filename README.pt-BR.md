🇧🇷 [Leia em Português](README.pt-BR.md) (esta página) | 🇺🇸 [English](README.md)

# TaskConnect

> Agendador de tarefas HTTP multitenant e disparador de webhooks projetado para hospedagem compartilhada PHP/MySQL tradicional.

> 🍵 **Parte da Iniciativa IROH** (*It Runs On Hostinger*)  
> Este projeto é um software livre, autohospedado, desenvolvido para hospedagem compartilhada convencional.  
> Saiba mais no hub central: [suporterfid/iroh](https://github.com/suporterfid/iroh) | [Manifesto](https://github.com/suporterfid/iroh/blob/main/MANIFESTO.md) | [Índice de Projetos](https://github.com/suporterfid/iroh/blob/main/PROJECTS.md) | [Especificação de Marca](https://github.com/suporterfid/iroh/blob/main/BRANDING.md)

---

## Visão Geral

O **TaskConnect** é um agendador de tarefas HTTP multitenant e motor de entrega de webhooks leve e autohospedado, desenvolvido para rodar com alta eficiência em plataformas de hospedagem compartilhada tradicionais (Hostinger, cPanel, Apache/Nginx) sem necessidade de daemons em segundo plano ou infraestrutura em nuvem cara. Construído com PHP 8.2+ (arquitetura backend em Laravel 12) e Vue 3 (interface SPA do operador), o TaskConnect oferece execução de rotinas agendadas, crons recorrentes e callbacks HTTP em planos de hospedagem econômicos equipados apenas com PHP, MySQL e um cron de 1 minuto.

Como projeto integrante da **Iniciativa IROH** ("It Runs On Hostinger"), o TaskConnect exemplifica os princípios de autossoberania, minimalismo de recursos e engenharia de alto desempenho sob medida para ambientes de hospedagem web acessíveis.

---

## Parte da Iniciativa IROH

O TaskConnect é desenvolvido sob a **Iniciativa IROH**, um ecossistema de softwares open-source projetados para permitir que desenvolvedores e pequenas equipes hospedem aplicações completas em contas padrão de cPanel / hospedagem compartilhada.

* **Hub Central**: [https://github.com/suporterfid/iroh](https://github.com/suporterfid/iroh)
* **Manifesto**: [Leia o Manifesto IROH](https://github.com/suporterfid/iroh/blob/main/MANIFESTO.md)
* **Índice de Projetos**: [Explore todos os projetos IROH](https://github.com/suporterfid/iroh/blob/main/PROJECTS.md)
* **Especificação de Marca**: [Identidade Visual e Sistema de Design](https://github.com/suporterfid/iroh/blob/main/BRANDING.md)

---

## Funcionalidades

- **Agendamento de Tarefas HTTP Multitenant**: Defina webhooks temporizados de execução única, agendamentos cron recorrentes ou execuções manuais isoladas por ambientes de clientes.
- **Arrendamento por Leases em MySQL (Sem Daemons)**: Motor de consumo concorrente (`scheduler:execute-due` / `scheduler:retry-due`) utilizando bloqueio de transação em MySQL (`lockForUpdate`) com recuperação configurável de leases expirados.
- **Proteção SSRF Avançada**: Transporte HTTP de saída com resolução DNS fixada (`OutboundPolicy`) que previne DNS rebinding, varredura de redes privadas, acesso a metadados de nuvem e chamadas internas não autorizadas.
- **Fila de Cartas Mortas (DLQ) e Alertas**: Gerenciamento completo de falhas terminais, inspeção de payload, reexecução manual via CLI/UI e notificações automáticas via e-mail/webhook por ambiente.
- **Execução em Pipelines DAG**: Encadeamento de tarefas em grafos de execução (`on_success`, `on_failure`, `depends_on`) com fusão de surtos (*coalescing*) e justiça por peso de workspace (*weighted round-robin*).
- **Segredos Criptografados e Redação de Dados**: Criptografia simétrica transparente em repouso para credenciais de endpoints, com redação automática em logs de execução e snapshots de auditoria.
- **Painel SPA Moderno em Vue 3**: Interface responsiva focada em conteúdo criada com Vue 3, Vite, Pinia e Tailwind CSS.
- **Autenticação Unificada (GrandpaSSOn)**: Integração nativa com o `suporterfid/grandpasson-ce` para autenticação OAuth2/OIDC, tokens JWT assinados e verificação de assinatura HMAC em callbacks.

---

## Requisitos

- **Ambiente de Produção (Hospedagem Compartilhada)**:
  - PHP 8.2+ com extensões PDO (`pdo_sqlite`, `pdo_mysql`)
  - MySQL 8.0+ ou MariaDB 10.5+
  - Apache ou LiteSpeed com `mod_rewrite` ativado (Document Root configurado para `public/`)
  - Cron operacional executável a cada minuto
- **Arquitetura de Banco de Dados Duplo**:
  - **SQLite**: Suportado para desenvolvimento local single-tenant, testes e implantações enxutas.
  - **MySQL / MariaDB**: Recomendado para ambientes de produção multitenant em hospedagem compartilhada.
- **Experiência de Desenvolvimento 100% Containerizada em Docker**:
  - Stack local completa contendo `web` (Nginx), `php` (PHP-FPM), `frontend` (Node 20 / servidor de dev Vite) e `db` (MySQL 8.0).
  - Todo o código é 100% testável, compilável e executável localmente em containers Docker Compose sem requerer PHP ou Node instalados na máquina hospedeira.
- **Paridade CLI Multiplataforma**:
  - Scripts de automação simétricos para PowerShell (`.\scripts\tc.ps1`) e Bash (`./scripts/tc.sh`) para gerenciamento de containers, suíte de testes, compilação de assets, migrações de banco e empacotamento de releases.

---

## Interface do Usuário e Sistema de Design

A interface frontend do TaskConnect segue estritamente a especificação de identidade visual inspirada no Notion da iniciativa IROH:
- **Contrato de Tokens CSS Semânticos**: Baseado em propriedades customizadas CSS (`:root`, `[data-theme='light']`, `[data-theme='dark']`), garantindo alternância de temas sem poluição na compilação.
- **Acessibilidade e Responsividade**: Total conformidade com diretrizes WCAG 2.2 AA para contraste, foco visível (`:focus-visible`) e layout responsivo mobile-first.
- **Referência de Design**: Veja a [Especificação de Marca e Identidade Visual](https://github.com/suporterfid/iroh/blob/main/BRANDING.md).

---

## Instalação

### Implantação em Hospedagem Compartilhada (cPanel / Hostinger)

1. **Download / Compilação do Pacote**:
   Baixe o arquivo ZIP da versão mais recente no repositório ou compile-o localmente executando:
   ```bash
   # Windows PowerShell
   .\scripts\tc.ps1 release

   # Linux / macOS
   ./scripts/tc.sh release
   ```
   O pacote ZIP pronto para produção será gerado no diretório `dist/`.

2. **Envio e Extração**:
   Envie o arquivo ZIP para sua conta de hospedagem via FTP/rsync ou Gerenciador de Arquivos do cPanel e extraia os arquivos no diretório de destino.

3. **Configuração do Document Root**:
   Configure o domínio ou subdomínio no cPanel para apontar o **Document Root** para o diretório `public/`.

4. **Configuração de Ambiente**:
   Copie `.env.example` para `.env` e ajuste as credenciais do seu ambiente de produção:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://taskconnect.seudominio.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nome_do_banco
   DB_USERNAME=usuario_do_banco
   DB_PASSWORD=senha_do_banco
   ```

5. **Migração do Banco de Dados e Admin**:
   Execute as migrações e crie a conta do administrador inicial via SSH ou script de terminal:
   ```bash
   php artisan migrate --force
   php artisan platform:bootstrap-admin admin@seudominio.com "SenhaSegura123!" --name="Admin"
   ```

6. **Configuração do Cron**:
   Cadastre as seguintes entradas no gerenciador de Cron da hospedagem compartilhada (para rodar a cada minuto):
   ```cron
   * * * * * /usr/bin/php /caminho/para/taskconnect/artisan scheduler:execute-due >/dev/null 2>&1
   * * * * * /usr/bin/php /caminho/para/taskconnect/artisan scheduler:retry-due >/dev/null 2>&1
   17 * * * * /usr/bin/php /caminho/para/taskconnect/artisan scheduler:maintenance >/dev/null 2>&1
   ```

---

## Inicio Rápido e DX Local (Docker)

Todas as operações locais são containerizadas. Não instale PHP ou Node diretamente na sua máquina física.

### Windows (PowerShell)
```powershell
Copy-Item .env.example .env
.\scripts\tc.ps1 up
.\scripts\tc.ps1 bootstrap
.\scripts\tc.ps1 artisan platform:bootstrap-admin admin@example.com "ChangeMeNow!" --name="Admin"
```

### Linux / macOS (Bash)
```bash
cp .env.example .env
./scripts/tc.sh up
./scripts/tc.sh bootstrap
./scripts/tc.sh artisan platform:bootstrap-admin admin@example.com "ChangeMeNow!" --name="Admin"
```

* **Aplicação SPA**: http://localhost:8080
* **Mailpit (Caixa de E-mails Dev)**: http://localhost:8025
* **Receiver (Alvo HTTP de Teste)**: http://localhost:8090

### Resumo de Comandos CLI

| Comando | Finalidade |
| :--- | :--- |
| `tc up` / `tc down` | Inicia ou interrompe os containers do ambiente Docker local |
| `tc test` | Executa a suíte de testes PHPUnit dentro do container |
| `tc composer ...` | Executa comandos do Composer dentro do container PHP |
| `tc artisan ...` | Executa comandos do Artisan dentro do container PHP |
| `tc npm ...` | Executa comandos do npm dentro do container Node |
| `tc release` | Empacota o arquivo ZIP de produção para hospedagem compartilhada em `dist/` |

---

## Autenticação e Single Sign-On (SSO)

O TaskConnect possui suporte nativo para autenticação unificada via [GrandpaSSOn](https://github.com/suporterfid/grandpasson-ce) (`suporterfid/grandpasson-ce`), o Provedor de Identidade central oficial do ecossistema IROH.

- **Suporte a OIDC / OAuth2**: Conecte o TaskConnect ao GrandpaSSOn para autenticar usuários através de um login centralizado.
- **JWT Assinado e Client Credentials**: A entrega de webhooks suporta autenticação via credenciais de cliente e cabeçalhos com JWTs assinados.
- **Verificação HMAC em Callbacks**: Valida a resposta e a origem dos webhooks com assinaturas HMAC e cabeçalhos customizados (`X-TC-Task-Id`, `X-TC-Workspace`).

---

## Integração com Outros Projetos IROH

O TaskConnect integra-se perfeitamente com os demais projetos da Iniciativa IROH:

* 🔐 [suporterfid/grandpasson-ce](https://github.com/suporterfid/grandpasson-ce) — Provedor de Identidade Central (SSO) e servidor OAuth2.
* 📝 [suporterfid/jotter](https://github.com/suporterfid/jotter) — Base de conhecimento em Markdown autohospedada com sincronização WebDAV.
* 📄 [suporterfid/ConvertDocToMd-PHP](https://github.com/suporterfid/ConvertDocToMd-PHP) — Conversor PHP direto de DOC/DOCX/PDF para Markdown.
* ⚡ [suporterfid/taskconnect](https://github.com/suporterfid/taskconnect) — Agendador de tarefas HTTP multitenant & disparador de webhooks (este projeto).
* 📊 [suporterfid/statusconnect](https://github.com/suporterfid/statusconnect) — Monitoramento de disponibilidade e página pública de status.
* 🔢 [suporterfid/tallymark](https://github.com/suporterfid/tallymark) — Contador de eventos e registrador minimalista de métricas.

---

## Contribuição

Contribuições são muito bem-vindas! Consulte nossas [Diretrizes de Contribuição](CONTRIBUTING.md) ou verifique o guia de governança centralizada no [Guia de Contribuição do Repositório IROH](https://github.com/suporterfid/iroh/blob/main/CONTRIBUTING.md).

---

## Licença

O TaskConnect é um software livre distribuído sob a licença [MIT License](LICENSE).
