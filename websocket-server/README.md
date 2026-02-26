# MySpace WebSocket Server

Servidor de WebSocket em tempo real para a aplicação MySpace com recursos similares ao Discord.

## 🚀 Features Implementadas

- ✅ **Conexões WebSocket em tempo real** com Socket.IO
- ✅ **Autenticação via JWT** integrada com sessões PHP
- ✅ **Salas de chat** (públicas e privadas)
- ✅ **Indicadores de online/offline**
- ✅ **Status de digitação** em tempo real
- ✅ **Histórico de mensagens** persistido em MySQL
- ✅ **Bridge Redis** para comunicação PHP ↔ Node.js
- ✅ **Validação de token** endpoint para PHP

## 📋 Pré-requisitos

- Node.js 16+ 
- MySQL 5.7+ ou 8.0+
- Redis 6.0+
- PHP 7.4+ (sistema existente)

## 🛠️ Instalação

1. **Instalar dependências:**
   ```bash
   cd websocket-server
   npm install
   ```

2. **Configurar variáveis de ambiente:**
   ```bash
   cp .env.example .env
   # Editar .env com suas configurações
   ```

3. **Iniciar Redis:**
   ```bash
   redis-server
   ```

4. **Iniciar servidor:**
   ```bash
   # Development
   npm run dev
   
   # Production
   npm start
   ```

## 🌐 Endpoints

- **WebSocket Server:** `ws://localhost:3002`
- **HTTP API:** `http://localhost:3002`
- **Health Check:** `http://localhost:3002/health`
- **Token Validation:** `POST http://localhost:3002/validate-token`

## 🔌 Eventos Socket.IO

### Client → Server
- `join_room` - Entrar em sala
- `send_message` - Enviar mensagem
- `typing` - Indicador de digitação
- `leave_room` - Sair da sala
- `get_friends` - Obter lista de amigos
- `get_online_users` - Obter usuários online

### Server → Client
- `chat_history` - Histórico de mensagens
- `new_message` - Nova mensagem recebida
- `user_joined` - Usuário entrou na sala
- `user_left` - Usuário saiu da sala
- `user_online` - Usuário ficou online
- `user_offline` - Usuário ficou offline
- `user_typing` - Usuário está digitando
- `user_stop_typing` - Usuário parou de digitar
- `friends_list` - Lista de amigos com status
- `online_users` - Lista de usuários online
- `error` - Erro na operação

## 🔧 Estrutura do Projeto

```
websocket-server/
├── server.js              # Servidor principal
├── package.json           # Dependências e scripts
├── config/               # Configurações
│   ├── config.js         # Configurações gerais
│   ├── database.js       # Conexão MySQL
│   └── redis.js         # Conexão Redis
├── middleware/           # Middlewares
│   └── auth.js          # Autenticação JWT
├── handlers/             # Event handlers
│   └── chatHandler.js    # Lógica de chat
└── .env.example         # Exemplo de variáveis
```

## 🔄 Fluxo de Autenticação

1. **PHP** gera JWT após login do usuário
2. **Frontend** envia JWT na conexão WebSocket
3. **Node.js** valida JWT e aceita conexão
4. **Socket** associado ao ID do usuário autenticado

## 📊 Performance

- **Conexões simultâneas:** ~10,000 por instância
- **Latência:** <50ms para mensagens locais
- **Persistência:** MySQL para mensagens, Redis para estado online
- **Protocolos:** WebSocket (primário), HTTP polling (fallback)

## 🛡️ Segurança

- Tokens JWT com expiração
- Validação de sessão PHP
- CORS configurado
- Input sanitization
- Prepared statements no MySQL

## 📈 Escalabilidade

Para escalar horizontalmente:
1. Configurar Redis Cluster
2. Balanceador de carga com sticky sessions
3. Múltiplas instâncias do servidor
4. Adapter Redis do Socket.IO para multi-node

## 🔍 Monitoramento

```bash
# Ver conexões ativas
redis-cli> monitor

# Ver processos Node.js
ps aux | grep node

# Testar servidor
curl http://localhost:3002/health
```