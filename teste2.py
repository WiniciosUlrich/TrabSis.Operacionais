import networkx as nx
import matplotlib.pyplot as plt
from matplotlib.patches import Rectangle, Circle
from collections import defaultdict

# === Dados fixos do sistema ===
recursos = {
    'R1': 2,  # 2 unidades
    'R2': 3,  # 3 unidades
    'R3': 3,  # 3 unidades
    'R4': 1   # 1 unidade
}

processos = ['P1', 'P2', 'P3', 'P4']

# Alocações (quem está usando o quê e quantas unidades)
alocacoes = {
    'R1': ['P2'],             # R1 com P2 (1 unidade)
    'R2': ['P1', 'P3'],       # R2 com P1 e P3 (2 unidades de 3)
    'R3': ['P3', 'P4'],       # R3 com P3 e P4 (2 unidades de 3)
    'R4': ['P4']              # R4 com P4 (1 unidade de 1)
}

# Requisições (quem está esperando por quê)
requisicoes = {
    'P1': ['R4'],             # P1 quer R4
    'P2': ['R1', 'R2', 'R1'], # P2 quer R1 e R2 (duas vezes R1)
    'P3': [],
    'P4': ['R3']              # P4 quer R3 (já possui, mas pode querer mais)
}

# === Criação do grafo ===
G = nx.MultiDiGraph()  # Usamos MultiDiGraph para permitir múltiplas arestas

# Adiciona nós
for r in recursos:
    G.add_node(r, tipo='recurso')
for p in processos:
    G.add_node(p, tipo='processo')

# Adiciona arestas de alocação (recurso -> processo)
for recurso, processos_alocados in alocacoes.items():
    for proc in processos_alocados:
        G.add_edge(recurso, proc)

# Adiciona arestas de requisição (processo -> recurso)
for processo, recs in requisicoes.items():
    for rec in recs:
        G.add_edge(processo, rec)

# === Função para verificar deadlock considerando as unidades de recursos ===
def detecta_deadlock_com_unidades(grafo, recursos, alocacoes, requisicoes):
    # Verifica todos os ciclos no grafo
    try:
        ciclos = list(nx.simple_cycles(grafo))
        
        for ciclo in ciclos:
            recursos_no_ciclo = set([n for n in ciclo if n.startswith('R')])
            processos_no_ciclo = set([n for n in ciclo if n.startswith('P')])

            # Verifica se o ciclo contém recursos e processos
            if recursos_no_ciclo and processos_no_ciclo:
                # Verifica se há uma alocação insuficiente de recursos para os processos no ciclo
                for processo in processos_no_ciclo:
                    for recurso in requisicoes[processo]:
                        if recurso in recursos_no_ciclo:
                            unidades_alocadas = sum([1 for p in alocacoes.get(recurso, []) if p == processo])
                            unidades_requisitadas = requisicoes[processo].count(recurso)

                            # Se o número de unidades requisitadas excede as unidades disponíveis, há deadlock
                            if unidades_alocadas + unidades_requisitadas > recursos[recurso]:
                                return True, ciclo
        return False, []
    except:
        return False, []

# === Visualização ===
pos = nx.spring_layout(G, seed=42)
plt.figure(figsize=(12, 10))
ax = plt.gca()

# Desenha nós personalizados
for node, (x, y) in pos.items():
    if G.nodes[node]['tipo'] == 'processo':
        ax.add_patch(Rectangle((x-0.05, y-0.05), 0.1, 0.1, color='skyblue', ec='black'))
        plt.text(x, y, node, ha='center', va='center', fontsize=10)
    else:  # recurso
        ax.add_patch(Circle((x, y), radius=0.07, color='lightgreen', ec='black'))
        plt.text(x, y+0.1, node, ha='center', va='center', fontsize=10)

        # Desenha bolinhas internas representando unidades (todas preenchidas)
        total_unidades = recursos[node]
        for i in range(total_unidades):
            dx = (i - total_unidades / 2) * 0.03 + 0.015
            dy = -0.08
            ax.add_patch(Circle((x + dx, y + dy), radius=0.01, color='black', ec='black'))

# Contar arestas paralelas para ajustar as curvas
edge_counts = defaultdict(int)

# Desenha arestas com flechas individuais
for edge in G.edges():
    edge_counts[edge] += 1
    count = edge_counts[edge]
    
    # Ajusta a curvatura baseada no número de arestas paralelas
    rad = 2 * (count - 1) if count > 1 else 0
    
    nx.draw_networkx_edges(
        G, pos,
        edgelist=[edge],
        arrows=True,
        ax=ax,
        arrowsize=20,
        connectionstyle=f'arc3,rad={rad}',
        edge_color='black'
    )

plt.axis('off')
plt.title("Grafo de Alocação de Recursos")
plt.show()

# === Resultado ===
estah_em_deadlock, ciclo = detecta_deadlock_com_unidades(G, recursos, alocacoes, requisicoes)

if estah_em_deadlock:
    print("⚠️ DEADLOCK detectado!")
    print("Ciclo envolvido:", ' -> '.join(ciclo))
else:
    print("✅ Nenhum deadlock detectado.")