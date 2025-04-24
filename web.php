<?php
// Função para salvar os resultados no arquivo JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_result') {
    $data = json_decode($_POST['data'], true);
    $result_path = __DIR__ . '/data/resultados.json';
    
    // Criar o diretório data se não existir
    if (!file_exists(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0755, true);
    }
    
    // Carregar resultados existentes
    $resultados = ['execucoes' => []];
    if (file_exists($result_path)) {
        $json_content = file_get_contents($result_path);
        if ($json_content) {
            $resultados = json_decode($json_content, true);
            if (!$resultados) {
                $resultados = ['execucoes' => []];
            }
        }
    }
    
    // Determinar próximo ID de execução
    $ultimo_id = 0;
    if (!empty($resultados["execucoes"])) {
        $ids = array_column($resultados["execucoes"], "id");
        $ultimo_id = !empty($ids) ? max($ids) : 0;
    }
    
    // Criar nova entrada
    $timestamp = date("Y-m-d H:i:s");
    $nova_execucao = [
        "id" => $ultimo_id + 1,
        "timestamp" => $timestamp,
        "deadlock_detectado" => $data["deadlock_detectado"],
        "ciclo" => $data["ciclo"],
        "recursos" => $data["recursos"],
        "processos" => $data["processos"],
        "alocacoes" => $data["alocacoes"],
        "requisicoes" => $data["requisicoes"]
    ];
    
    // Adicionar à lista
    $resultados["execucoes"][] = $nova_execucao;
    
    // Salvar de volta ao arquivo
    file_put_contents($result_path, json_encode($resultados, JSON_PRETTY_PRINT));
    
    // Retornar o ID da execução
    echo json_encode(["id" => $ultimo_id + 1]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Grafo de Alocação de Recursos</title>
    <script src="https://cdn.jsdelivr.net/pyodide/v0.23.4/full/pyodide.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        textarea {
            font-family: monospace;
            resize: vertical;
        }
        #error-message {
            color: #dc2626;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-4xl w-full p-6 bg-white rounded-lg shadow-lg">
        <h1 class="text-2xl font-bold mb-4 text-center">Gerador de Grafo de Alocação de Recursos</h1>
        <p class="text-gray-600 mb-6">Insira cada item em uma nova linha nos campos abaixo.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Recursos (ex: R1:2)</label>
                <textarea id="recursos-input" class="mt-1 block w-full border border-gray-300 rounded-md p-2" rows="5" placeholder="R1:2&#10;R2:1"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Processos (ex: P1)</label>
                <textarea id="processos-input" class="mt-1 block w-full border border-gray-300 rounded-md p-2" rows="5" placeholder="P1&#10;P2"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Alocações (ex: R1:P1)</label>
                <textarea id="alocacoes-input" class="mt-1 block w-full border border-gray-300 rounded-md p-2" rows="5" placeholder="R1:P1&#10;R2:P2"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Requisições (ex: P1:R1)</label>
                <textarea id="requisicoes-input" class="mt-1 block w-full border border-gray-300 rounded-md p-2" rows="5" placeholder="P1:R1&#10;P2:R2"></textarea>
            </div>
        </div>
        <div class="mt-6 text-center">
            <button onclick="generateGraph()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">Gerar Grafo</button>
        </div>
        <div id="error-message" class="mt-4 text-center"></div>
        <div id="save-result" class="mt-2 text-center text-sm"></div>
        <div id="graph-output" class="mt-6 flex justify-center"></div>
    </div>

    <script>
        async function loadPyodideAndPackages() {
            let pyodide = await loadPyodide();
            await pyodide.loadPackage(['matplotlib', 'networkx']);
            return pyodide;
        }

        let pyodideReady = loadPyodideAndPackages();

        // Função para salvar resultados via AJAX
        async function saveResult(config, resultObj) {
            const saveData = {
                recursos: config.recursos,
                processos: config.processos,
                alocacoes: config.alocacoes,
                requisicoes: config.requisicoes,
                deadlock_detectado: resultObj.deadlock_detectado,
                ciclo: resultObj.ciclo
            };
            
            try {
                const response = await fetch('web.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=save_result&data=${encodeURIComponent(JSON.stringify(saveData))}`
                });
                
                if (!response.ok) {
                    throw new Error('Resposta da rede não foi ok');
                }
                
                const data = await response.json();
                document.getElementById('save-result').innerHTML = `<span class="text-blue-600">Resultado salvo com ID #${data.id}</span>`;
                return data.id;
            } catch (error) {
                console.error('Erro ao salvar resultado:', error);
                document.getElementById('save-result').innerHTML = `<span class="text-red-600">Erro ao salvar resultado: ${error.message}</span>`;
                return null;
            }
        }

        async function generateGraph() {
            const errorMessage = document.getElementById('error-message');
            const saveResultDiv = document.getElementById('save-result');
            const graphOutput = document.getElementById('graph-output');
            errorMessage.textContent = '';
            saveResultDiv.innerHTML = '';
            graphOutput.innerHTML = '<div class="text-center p-4">Gerando gráfico...</div>';

            // Obter valores de entrada
            const recursosText = document.getElementById('recursos-input').value.trim();
            const processosText = document.getElementById('processos-input').value.trim();
            const alocacoesText = document.getElementById('alocacoes-input').value.trim();
            const requisicoesText = document.getElementById('requisicoes-input').value.trim();

            // Inicializar estrutura JSON
            let config = {
                recursos: {},
                processos: [],
                alocacoes: {},
                requisicoes: {}
            };

            // Analisar recursos (ex: R1:2)
            if (recursosText) {
                const lines = recursosText.split('\n').map(line => line.trim()).filter(line => line);
                for (const line of lines) {
                    if (!/^[A-Za-z0-9]+:\d+$/.test(line)) {
                        errorMessage.textContent = `Formato de recurso inválido: "${line}". Use o formato "R1:2".`;
                        graphOutput.innerHTML = '';
                        return;
                    }
                    const [id, units] = line.split(':');
                    config.recursos[id] = parseInt(units);
                }
            }

            // Analisar processos (ex: P1)
            if (processosText) {
                const lines = processosText.split('\n').map(line => line.trim()).filter(line => line);
                for (const line of lines) {
                    if (!/^[A-Za-z0-9]+$/.test(line)) {
                        errorMessage.textContent = `Formato de processo inválido: "${line}". Use o formato "P1".`;
                        graphOutput.innerHTML = '';
                        return;
                    }
                    config.processos.push(line);
                }
            }

            // Analisar alocações (ex: R1:P1)
            if (alocacoesText) {
                const lines = alocacoesText.split('\n').map(line => line.trim()).filter(line => line);
                for (const line of lines) {
                    if (!/^[A-Za-z0-9]+:[A-Za-z0-9]+$/.test(line)) {
                        errorMessage.textContent = `Formato de alocação inválido: "${line}". Use o formato "R1:P1".`;
                        graphOutput.innerHTML = '';
                        return;
                    }
                    const [resource, process] = line.split(':');
                    if (!config.alocacoes[resource]) {
                        config.alocacoes[resource] = [];
                    }
                    config.alocacoes[resource].push(process);
                }
            }

            // Analisar requisições (ex: P1:R1)
            if (requisicoesText) {
                const lines = requisicoesText.split('\n').map(line => line.trim()).filter(line => line);
                for (const line of lines) {
                    if (!/^[A-Za-z0-9]+:[A-Za-z0-9]+$/.test(line)) {
                        errorMessage.textContent = `Formato de requisição inválido: "${line}". Use o formato "P1:R1".`;
                        graphOutput.innerHTML = '';
                        return;
                    }
                    const [process, resource] = line.split(':');
                    if (!config.requisicoes[process]) {
                        config.requisicoes[process] = [];
                    }
                    config.requisicoes[process].push(resource);
                }
            }

            // Validar que pelo menos um campo não está vazio
            if (!recursosText && !processosText && !alocacoesText && !requisicoesText) {
                errorMessage.textContent = 'Por favor, preencha pelo menos um campo.';
                graphOutput.innerHTML = '';
                return;
            }

            let pyodide = await pyodideReady;
            try {
                const result = await pyodide.runPythonAsync(`
import json
import networkx as nx
import matplotlib.pyplot as plt
from matplotlib.patches import Rectangle, Circle
from collections import defaultdict
import base64
from io import BytesIO

# Get JSON from JavaScript
config = ${JSON.stringify(config)}

recursos = config['recursos']
processos = config['processos']
alocacoes = config['alocacoes']
requisicoes = config['requisicoes']

# Create graph
G = nx.MultiDiGraph()

# Add nodes
for r in recursos:
    G.add_node(r, tipo='recurso')
for p in processos:
    G.add_node(p, tipo='processo')

# Add allocation edges (recurso -> processo)
for recurso, processos_alocados in alocacoes.items():
    for proc in processos_alocados:
        G.add_edge(recurso, proc)

# Add request edges (processo -> recurso)
for processo, recs in requisicoes.items():
    for rec in recs:
        G.add_edge(processo, rec)

# Função para verificar deadlock considerando as unidades de recursos
def detecta_deadlock_com_unidades(grafo, recursos, alocacoes, requisicoes):
    # Calcula unidades alocadas por recurso
    unidades_alocadas = {r: 0 for r in recursos}
    for recurso, processos_alocados in alocacoes.items():
        unidades_alocadas[recurso] = len(processos_alocados)  # Assume 1 unidade por processo

    # Calcula unidades disponíveis
    unidades_disponiveis = {r: recursos[r] - unidades_alocadas.get(r, 0) for r in recursos}

    # Verifica ciclos
    try:
        ciclos = list(nx.simple_cycles(grafo))
        for ciclo in ciclos:
            recursos_no_ciclo = set(n for n in ciclo if n.startswith('R'))
            processos_no_ciclo = set(n for n in ciclo if n.startswith('P'))

            if recursos_no_ciclo and processos_no_ciclo:
                # Verifica se as requisições excedem as unidades disponíveis
                for processo in processos_no_ciclo:
                    for recurso in requisicoes.get(processo, []):
                        if recurso in recursos_no_ciclo:
                            unidades_requisitadas = requisicoes[processo].count(recurso)
                            if unidades_requisitadas > unidades_disponiveis[recurso]:
                                return True, ciclo
        return False, []
    except nx.NetworkXNoCycle:
        return False, []

# Detecta deadlock
estah_em_deadlock, ciclo = detecta_deadlock_com_unidades(G, recursos, alocacoes, requisicoes)

# Criação do título com informação de deadlock
titulo = "Grafo de Alocação de Recursos"
if estah_em_deadlock:
    titulo += " (DEADLOCK DETECTADO)"
    ciclo_texto = " -> ".join(ciclo)
else:
    ciclo_texto = ""

# Visualization
pos = nx.circular_layout(G, scale=1)
plt.figure(figsize=(10, 8))
ax = plt.gca()

# Draw nodes
for node, (x, y) in pos.items():
    if G.nodes[node]['tipo'] == 'processo':
        color = 'red' if estah_em_deadlock and node in ciclo else 'skyblue'
        ax.add_patch(Rectangle((x-0.05, y-0.05), 0.1, 0.1, color=color, ec='black'))
        plt.text(x, y, node, ha='center', va='center', fontsize=10)
    else:  # recurso
        color = 'red' if estah_em_deadlock and node in ciclo else 'lightgreen'
        ax.add_patch(Circle((x, y), radius=0.07, color=color, ec='black'))
        plt.text(x, y+0.1, node, ha='center', va='center', fontsize=10)
        total_unidades = recursos[node]
        for i in range(total_unidades):
            dx = (i - total_unidades / 2) * 0.03 + 0.015
            dy = -0.08
            ax.add_patch(Circle((x + dx, y + dy), radius=0.01, color='black', ec='black'))

# Count parallel edges for curvature
edge_counts = defaultdict(int)
for edge in G.edges():
    edge_counts[edge] += 1
    count = edge_counts[edge]
    rad = 0.5 * (count - 1) if count > 1 else 0.3
    
    # Verifica se a aresta faz parte do ciclo de deadlock
    is_in_cycle = False
    if estah_em_deadlock and edge[0] in ciclo and edge[1] in ciclo:
        idx0 = ciclo.index(edge[0])
        idx1 = ciclo.index(edge[1])
        if idx1 == (idx0 + 1) % len(ciclo):
            is_in_cycle = True
            
    edge_color = 'red' if is_in_cycle else 'black'
    edge_width = 2.0 if is_in_cycle else 1.0
    
    nx.draw_networkx_edges(
        G, pos,
        edgelist=[edge],
        arrows=True,
        ax=ax,
        arrowsize=10,
        connectionstyle=f'arc3,rad={rad}',
        edge_color=edge_color,
        width=edge_width
    )

plt.title(titulo)

# Se houver deadlock e ciclo, adicionar texto explicando o ciclo
if estah_em_deadlock and ciclo:
    plt.figtext(0.5, 0.01, f"Ciclo de deadlock: {ciclo_texto}", 
                ha="center", fontsize=10, 
                bbox={"facecolor":"orange", "alpha":0.5, "pad":5})

# Ajusta limites e margens do gráfico
plt.axis('on')
plt.tight_layout()

# Save to BytesIO and encode to base64
buf = BytesIO()
plt.savefig(buf, format='png', bbox_inches='tight')
buf.seek(0)
img_str = base64.b64encode(buf.getvalue()).decode('utf-8')
plt.close()

# Preparar resultado para retorno ao JavaScript
resultado = {
    "img_base64": img_str,
    "deadlock_detectado": bool(estah_em_deadlock),
    "ciclo": ciclo if estah_em_deadlock else []
}

# Retornar o resultado como JSON string
json.dumps(resultado)
                `);
                
                // Analisa o resultado JSON
                const resultObj = JSON.parse(result);
                
                // Exibe imagem
                graphOutput.innerHTML = `<img src="data:image/png;base64,${resultObj.img_base64}" alt="Grafo de Alocação de Recursos" class="max-w-full">`;
                
                // Exibe mensagem sobre deadlock
                if (resultObj.deadlock_detectado) {
                    errorMessage.innerHTML = `<span class="text-red-600 font-bold">!! DEADLOCK detectado!</span><br>Ciclo envolvido: ${resultObj.ciclo.join(' -> ')}`;
                } else {
                    errorMessage.innerHTML = `<span class="text-green-600 font-bold">-- Nenhum deadlock detectado.</span>`;
                }
                
                // Salva o resultado no arquivo JSON
                const execId = await saveResult(config, resultObj);
            } catch (e) {
                errorMessage.textContent = 'Erro ao gerar gráfico: ' + e.message;
                graphOutput.innerHTML = '';
                console.error(e);
            }
        }
    </script>
</body>
</html>