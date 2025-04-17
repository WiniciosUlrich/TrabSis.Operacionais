import networkx as nx
import matplotlib.pyplot as plt
from matplotlib.patches import Rectangle, Circle

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
    'R2': ['P1'],             # R2 com P1 (1 unidade de 3)
    'R3': ['P3'],             # R3 com P3 (1 unidade de 3)
    'R4': []                  # R4 livre
}

# Requisições (quem está esperando por quê)
requisicoes = {
    'P1': ['R3'],             # P1 quer R3
    'P2': [],
    'P3': ['R4'],            # P3 quer R4
    'P4': ['R1']             # P4 quer R1
}

# === Criação do grafo ===
G = nx.DiGraph()

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

# === Verificação de deadlock ===
def detecta_deadlock(grafo):
    try:
        ciclos = list(nx.simple_cycles(grafo))
        for ciclo in ciclos:
            tipos = set(['recurso' if n.startswith('R') else 'processo' for n in ciclo])
            if 'recurso' in tipos and 'processo' in tipos:
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

# Desenha arestas
nx.draw_networkx_edges(G, pos, arrows=True, ax=ax, arrowsize=20)

plt.axis('off')
plt.title("Grafo de Alocação de Recursos (com Unidades)")
plt.show()

# === Resultado ===
estah_em_deadlock, ciclo = detecta_deadlock(G)

if estah_em_deadlock:
    print("⚠️ DEADLOCK detectado!")
    print("Ciclo envolvido:", ' -> '.join(ciclo))
else:
    print("✅ Nenhum deadlock detectado.")
