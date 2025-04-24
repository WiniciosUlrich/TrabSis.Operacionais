<?php
// index.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Allocation Graph Generator</title>
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
        <h1 class="text-2xl font-bold mb-4 text-center">Resource Allocation Graph Generator</h1>
        <p class="text-gray-600 mb-6">Enter each item on a new line in the fields below.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Resources (e.g., R1:2)</label>
                <textarea id="recursos-input" class="mt-1 block w-full border border-gray-300 rounded-md p-2" rows="5" placeholder="R1:2&#10;R2:1"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Processes (e.g., P1)</label>
                <textarea id="processos-input" class="mt-1 block w-full border border-gray-300 rounded-md p-2" rows="5" placeholder="P1&#10;P2"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Allocations (e.g., R1:P1)</label>
                <textarea id="alocacoes-input" class="mt-1 block w-full border border-gray-300 rounded-md p-2" rows="5" placeholder="R1:P1&#10;R2:P2"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Requests (e.g., P1:R1)</label>
                <textarea id="requisicoes-input" class="mt-1 block w-full border border-gray-300 rounded-md p-2" rows="5" placeholder="P1:R1&#10;P2:R2"></textarea>
            </div>
        </div>
        <div class="mt-6 text-center">
            <button onclick="generateGraph()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">Generate Graph</button>
        </div>
        <div id="error-message" class="mt-4 text-center"></div>
        <div id="graph-output" class="mt-6 flex justify-center"></div>
    </div>

    <script>
        async function loadPyodideAndPackages() {
            let pyodide = await loadPyodide();
            await pyodide.loadPackage(['matplotlib', 'networkx']);
            return pyodide;
        }

        let pyodideReady = loadPyodideAndPackages();

        async function generateGraph() {
            const errorMessage = document.getElementById('error-message');
            const graphOutput = document.getElementById('graph-output');
            errorMessage.textContent = '';
            graphOutput.innerHTML = '';

            // Get input values
            const recursosText = document.getElementById('recursos-input').value.trim();
            const processosText = document.getElementById('processos-input').value.trim();
            const alocacoesText = document.getElementById('alocacoes-input').value.trim();
            const requisicoesText = document.getElementById('requisicoes-input').value.trim();

            // Initialize JSON structure
            let config = {
                recursos: {},
                processos: [],
                alocacoes: {},
                requisicoes: {}
            };

            // Parse recursos (e.g., R1:2)
            if (recursosText) {
                const lines = recursosText.split('\n').map(line => line.trim()).filter(line => line);
                for (const line of lines) {
                    if (!/^[A-Za-z0-9]+:\d+$/.test(line)) {
                        errorMessage.textContent = `Invalid resource format: "${line}". Use "R1:2" format.`;
                        return;
                    }
                    const [id, units] = line.split(':');
                    config.recursos[id] = parseInt(units);
                }
            }

            // Parse processos (e.g., P1)
            if (processosText) {
                const lines = processosText.split('\n').map(line => line.trim()).filter(line => line);
                for (const line of lines) {
                    if (!/^[A-Za-z0-9]+$/.test(line)) {
                        errorMessage.textContent = `Invalid process format: "${line}". Use "P1" format.`;
                        return;
                    }
                    config.processos.push(line);
                }
            }

            // Parse alocacoes (e.g., R1:P1)
            if (alocacoesText) {
                const lines = alocacoesText.split('\n').map(line => line.trim()).filter(line => line);
                for (const line of lines) {
                    if (!/^[A-Za-z0-9]+:[A-Za-z0-9]+$/.test(line)) {
                        errorMessage.textContent = `Invalid allocation format: "${line}". Use "R1:P1" format.`;
                        return;
                    }
                    const [resource, process] = line.split(':');
                    if (!config.alocacoes[resource]) {
                        config.alocacoes[resource] = [];
                    }
                    config.alocacoes[resource].push(process);
                }
            }

            // Parse requisicoes (e.g., P1:R1)
            if (requisicoesText) {
                const lines = requisicoesText.split('\n').map(line => line.trim()).filter(line => line);
                for (const line of lines) {
                    if (!/^[A-Za-z0-9]+:[A-Za-z0-9]+$/.test(line)) {
                        errorMessage.textContent = `Invalid request format: "${line}". Use "P1:R1" format.`;
                        return;
                    }
                    const [process, resource] = line.split(':');
                    if (!config.requisicoes[process]) {
                        config.requisicoes[process] = [];
                    }
                    config.requisicoes[process].push(resource);
                }
            }

            // Validate that at least one field is non-empty
            if (!recursosText && !processosText && !alocacoesText && !requisicoesText) {
                errorMessage.textContent = 'Please fill in at least one field.';
                return;
            }

            let pyodide = await pyodideReady;
            try {
                const imgStr = await pyodide.runPythonAsync(`
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

# Visualization
pos = nx.circular_layout(G, scale=1)
plt.figure(figsize=(10, 8))
ax = plt.gca()

# Draw nodes
for node, (x, y) in pos.items():
    if G.nodes[node]['tipo'] == 'processo':
        ax.add_patch(Rectangle((x-0.05, y-0.05), 0.1, 0.1, color='skyblue', ec='black'))
        plt.text(x, y, node, ha='center', va='center', fontsize=10)
    else:  # recurso
        ax.add_patch(Circle((x, y), radius=0.07, color='lightgreen', ec='black'))
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
    rad = 1 * (count - 1) if count > 1 else 0.5
    nx.draw_networkx_edges(
        G, pos,
        edgelist=[edge],
        arrows=True,
        ax=ax,
        arrowsize=10,
        connectionstyle=f'arc3,rad={rad}',
        edge_color='black'
    )

plt.title("Grafo de Alocação de Recursos")

# Save to BytesIO and encode to base64
buf = BytesIO()
plt.savefig(buf, format='png', bbox_inches='tight')
buf.seek(0)
img_str = base64.b64encode(buf.getvalue()).decode('utf-8')
plt.close()

img_str
                `);
                graphOutput.innerHTML = `<img src="data:image/png;base64,${imgStr}" alt="Resource Allocation Graph" class="max-w-full">`;
            } catch (e) {
                errorMessage.textContent = 'Error generating graph: ' + e.message;
            }
        }
    </script>
</body>
</html>