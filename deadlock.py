import os
import datetime
import json
import networkx as nx
import matplotlib.pyplot as plt
from matplotlib.patches import Rectangle, Circle
from collections import defaultdict

script_dir = os.path.dirname(os.path.abspath(__file__))
json_path = os.path.join(script_dir, 'data', 'dados.json')

results_path = os.path.join(script_dir, 'data', 'resultados.json')

try:
    with open(json_path, 'r') as f:
        config = json.load(f)
except FileNotFoundError:
    print(f"Erro: Arquivo não encontrado: {json_path}")
    print(f"Diretório atual: {os.getcwd()}")
    exit(1)
except json.JSONDecodeError:
    print(f"Erro: Formato JSON inválido no arquivo: {json_path}")
    exit(1)

# Função para carregar os resultados anteriores
def carregar_resultados():
    if os.path.exists(results_path):
        try:
            with open(results_path, 'r') as f:
                return json.load(f)
        except:
            return {"execucoes": []}
    else:
        return {"execucoes": []}

# Função para salvar os resultados
def salvar_resultado(resultado):
    resultados = carregar_resultados()
    
    # Determinar próximo ID de execução
    ultimo_id = 0
    if resultados["execucoes"]:
        ids = [exec["id"] for exec in resultados["execucoes"]]
        ultimo_id = max(ids)
    
    # Adicionar timestamp
    timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    
    # Criar nova entrada
    nova_execucao = {
        "id": ultimo_id + 1,
        "timestamp": timestamp,
        "deadlock_detectado": resultado["deadlock_detectado"],
        "ciclo": resultado["ciclo"] if resultado["deadlock_detectado"] else [],
        "recursos": config["recursos"],
        "processos": config["processos"],
        "alocacoes": config["alocacoes"],
        "requisicoes": config["requisicoes"]
    }
    
    resultados["execucoes"].append(nova_execucao)
    
    # Salvar de volta ao arquivo
    with open(results_path, 'w') as f:
        json.dump(resultados, f, indent=4)
    
    return ultimo_id + 1

recursos = config['recursos']
processos = config['processos']
alocacoes = config['alocacoes']
requisicoes = config['requisicoes']

# Criação do grafo
G = nx.MultiDiGraph()

# Adiciona nós
for r in recursos:
    G.add_node(r, tipo='recurso')
for p in processos:
    G.add_node(p, tipo='processo')

# Alocações (quem está usando o quê)
# Requisições (quem está esperando por quê)

# Adiciona arestas de alocação (recurso -> processo)
for recurso, processos_alocados in alocacoes.items():
    for proc in processos_alocados:
        G.add_edge(recurso, proc)

# Adiciona arestas de requisição (processo -> recurso)
for processo, recs in requisicoes.items():
    for rec in recs:
        G.add_edge(processo, rec)

#Função para verificar deadlock considerando as unidades de recursos
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

# Visualização
pos = nx.circular_layout(G, scale=1)  # scale controla o tamanho total do layout
plt.figure(figsize=(25, 20))
ax = plt.gca()


for node, (x, y) in pos.items(): # Desenha nós
    if G.nodes[node]['tipo'] == 'processo':
        ax.add_patch(Rectangle((x-0.05, y-0.05), 0.1, 0.1, color='skyblue', ec='black'))
        plt.text(x, y, node, ha='center', va='center', fontsize=10)
    else:  # recurso
        ax.add_patch(Circle((x, y), radius=0.07, color='lightgreen', ec='black'))
        plt.text(x, y+0.1, node, ha='center', va='center', fontsize=10)

       
        total_unidades = recursos[node]  # Desenha bolinhas internas representando unidades
        for i in range(total_unidades):
            dx = (i - total_unidades / 2) * 0.03 + 0.015
            dy = -0.08
            ax.add_patch(Circle((x + dx, y + dy), radius=0.01, color='black', ec='black'))

# Contar arestas paralelas para ajustar as curvas
edge_counts = defaultdict(int)

# Desenha arestas individuai
for edge in G.edges():
    edge_counts[edge] += 1
    count = edge_counts[edge]
    
    # Ajusta a curvatura
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
plt.show()

#Resultado
estah_em_deadlock, ciclo = detecta_deadlock_com_unidades(G, recursos, alocacoes, requisicoes)

# Registra o resultado no arquivo JSON
resultado = {
    "deadlock_detectado": estah_em_deadlock,
    "ciclo": ciclo
}
id_execucao = salvar_resultado(resultado)

if estah_em_deadlock:
    print(f"!! DEADLOCK detectado! (Execução #{id_execucao})")
    print("Ciclo envolvido:", ' -> '.join(ciclo))
    print(f"Resultado salvo em {results_path}")
else:
    print(f"-- Nenhum deadlock detectado. (Execução #{id_execucao})")
    print(f"Resultado salvo em {results_path}")