@extends('hr.layouts.app')
@section('title', 'Organization Chart')
@section('heading', 'Organization Chart')

@section('content')
<div class="space-y-4" x-data="orgChartApp()" x-init="init()">

    {{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-3.5 flex flex-wrap items-center gap-3">

        {{-- Module filter --}}
        <div class="flex items-center gap-2">
            <i class="fas fa-cubes text-[#1B1444] text-xs"></i>
            <select id="filter-module" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:border-[#1B1444]"
                    @change="onModuleChange()" x-model="filters.moduleId">
                <option value="">All Modules</option>
                @foreach($modules as $mod)
                <option value="{{ $mod->id }}" {{ $selectedModuleId == $mod->id ? 'selected' : '' }}>{{ $mod->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Department filter (populated via AJAX) --}}
        <div class="flex items-center gap-2" x-show="deptOptions.length > 0">
            <i class="fas fa-building text-blue-400 text-xs"></i>
            <select class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:border-[#1B1444]"
                    @change="applyFilters()" x-model="filters.deptId">
                <option value="">All Departments</option>
                <template x-for="d in deptOptions" :key="d.id">
                    <option :value="d.id" x-text="d.name"></option>
                </template>
            </select>
        </div>

        {{-- Position filter (populated via AJAX) --}}
        <div class="flex items-center gap-2" x-show="posOptions.length > 0">
            <i class="fas fa-briefcase text-orange-400 text-xs"></i>
            <select class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:border-[#1B1444]"
                    @change="applyFilters()" x-model="filters.posId">
                <option value="">All Positions</option>
                <template x-for="p in posOptions" :key="p.id">
                    <option :value="p.id" x-text="p.name"></option>
                </template>
            </select>
        </div>

        <div class="flex-1"></div>

        {{-- Controls --}}
        <div class="flex items-center gap-1">
            <button @click="zoom(0.1)" title="Zoom in"
                    class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:border-[#1B1444] hover:text-[#1B1444] transition-colors text-xs">
                <i class="fas fa-plus"></i>
            </button>
            <button @click="zoom(-0.1)" title="Zoom out"
                    class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:border-[#1B1444] hover:text-[#1B1444] transition-colors text-xs">
                <i class="fas fa-minus"></i>
            </button>
            <button @click="resetView()" title="Reset view"
                    class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:border-[#1B1444] hover:text-[#1B1444] transition-colors text-xs">
                <i class="fas fa-compress-arrows-alt"></i>
            </button>
            <button @click="expandAll()" title="Expand all"
                    class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:border-[#1B1444] hover:text-[#1B1444] transition-colors ml-1">
                <i class="fas fa-expand-alt mr-1"></i>Expand All
            </button>
            <button @click="collapseAll()" title="Collapse all"
                    class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:border-[#1B1444] hover:text-[#1B1444] transition-colors">
                <i class="fas fa-compress-alt mr-1"></i>Collapse
            </button>
        </div>

        {{-- Legend --}}
        <div class="flex items-center gap-3 border-l border-gray-200 pl-3">
            @foreach(['primary'=>['#4F46E5','Primary'],'secondary'=>['#3B82F6','Secondary'],'temporary'=>['#F97316','Temp'],'acting'=>['#A855F7','Acting'],'project_based'=>['#14B8A6','Project']] as $t=>[$c,$l])
            <div class="flex items-center gap-1 text-[10px] text-gray-500">
                <div class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $c }};"></div>
                {{ $l }}
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Stats bar ────────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 px-1 text-xs text-gray-500">
        <span x-text="`${nodeCount} employee${nodeCount !== 1 ? 's' : ''}`"></span>
        <span class="text-gray-300">·</span>
        <span x-text="`${rootCount} root node${rootCount !== 1 ? 's' : ''}`"></span>
        <span class="text-gray-300">·</span>
        <span>Drag to pan · Scroll to zoom · Click node to expand/collapse</span>
    </div>

    {{-- ── Canvas ───────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden relative"
         style="height: 640px;">

        {{-- Loading overlay --}}
        <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-white/80 z-20">
            <div class="flex items-center gap-3 text-gray-500">
                <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <span class="text-sm">Loading chart...</span>
            </div>
        </div>

        {{-- Empty state --}}
        <div x-show="!loading && nodeCount === 0"
             class="absolute inset-0 flex flex-col items-center justify-center text-center p-8">
            <i class="fas fa-sitemap text-5xl text-gray-200 mb-4 block"></i>
            <p class="text-gray-400 text-sm mb-2">No employees match the current filters</p>
            <button @click="clearFilters()" class="text-sm text-[#F7941D] hover:underline">Clear filters</button>
        </div>

        {{-- Drag canvas --}}
        <div id="org-viewport"
             class="absolute inset-0 overflow-hidden cursor-grab active:cursor-grabbing"
             @mousedown="startDrag($event)"
             @mousemove="doDrag($event)"
             @mouseup="endDrag()"
             @mouseleave="endDrag()"
             @wheel.prevent="onWheel($event)"
             @touchstart.prevent="touchStart($event)"
             @touchmove.prevent="touchMove($event)"
             @touchend="touchEnd()">
            {{-- SVG connector layer --}}
            <svg id="org-svg"
                 class="absolute top-0 left-0 pointer-events-none overflow-visible"
                 style="width:100%;height:100%;">
                <g id="svg-connections"></g>
            </svg>
            {{-- Node layer --}}
            <div id="org-nodes" class="absolute top-0 left-0"
                 style="transform-origin: 0 0;">
            </div>
        </div>
    </div>

    {{-- ── Employee Popup ───────────────────────────────────────────────────── --}}
    <div x-show="popup.visible"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed z-50 bg-white rounded-xl shadow-2xl border border-gray-100 w-64 pointer-events-auto"
         :style="`top: ${popup.y}px; left: ${popup.x}px;`"
         @click.outside="popup.visible = false">
        <div class="p-4">
            {{-- Header --}}
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold text-sm flex-shrink-0"
                     :style="`background-color: ${popup.moduleColor || '#1B1444'};`"
                     x-text="popup.initials"></div>
                <div class="min-w-0">
                    <div class="font-semibold text-gray-800 text-sm truncate" x-text="popup.name"></div>
                    <div class="text-xs text-gray-400 truncate" x-text="popup.position || popup.roleLabel || '—'"></div>
                </div>
                <button @click="popup.visible = false" class="ml-auto text-gray-300 hover:text-gray-500 flex-shrink-0">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            {{-- Details --}}
            <div class="space-y-1.5 border-t border-gray-100 pt-3">
                <div x-show="popup.module" class="flex items-center gap-2 text-xs text-gray-600">
                    <i class="fas fa-cube text-[#1B1444] text-[10px] w-3"></i>
                    <span x-text="popup.module"></span>
                </div>
                <div x-show="popup.department" class="flex items-center gap-2 text-xs text-gray-600">
                    <i class="fas fa-building text-blue-400 text-[10px] w-3"></i>
                    <span x-text="popup.department"></span>
                </div>
                <div x-show="popup.role" class="flex items-center gap-2 text-xs text-gray-600">
                    <i class="fas fa-shield-alt text-indigo-400 text-[10px] w-3"></i>
                    <span x-text="popup.role"></span>
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-600">
                    <i class="fas fa-lock text-gray-300 text-[10px] w-3"></i>
                    <span x-text="popup.access"></span>
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-600">
                    <i class="fas fa-calendar text-gray-300 text-[10px] w-3"></i>
                    <span x-text="popup.assignedAt"></span>
                </div>
            </div>
            {{-- Actions --}}
            <div class="border-t border-gray-100 pt-3 mt-3 flex gap-2">
                <a :href="popup.url"
                   class="flex-1 text-center text-xs bg-[#1B1444] text-white px-3 py-1.5 rounded-lg hover:bg-[#2D2467] transition-colors">
                    View Profile
                </a>
                <a :href="`/hr/workforce/wizard?employee_id=${popup.employeeId}`"
                   class="flex-1 text-center text-xs border border-[#F7941D] text-[#F7941D] px-3 py-1.5 rounded-lg hover:bg-orange-50 transition-colors">
                    + Assignment
                </a>
            </div>
        </div>
    </div>

</div>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// Org Chart — pure JS tree renderer
// ─────────────────────────────────────────────────────────────────────────────

const INITIAL_TREE = @json($treeData);
const AJAX_URL     = "{{ route('hr.workforce.org-chart-data') }}";

const NODE_W       = 200;  // card width
const NODE_H       = 88;   // card height
const H_GAP        = 24;   // horizontal gap between siblings
const V_GAP        = 72;   // vertical gap between levels

const TYPE_COLORS  = {
    primary:       '#4F46E5',
    secondary:     '#3B82F6',
    temporary:     '#F97316',
    acting:        '#A855F7',
    project_based: '#14B8A6',
};

function orgChartApp() {
    return {
        // State
        loading:    false,
        filters:    { moduleId: '{{ $selectedModuleId ?? '' }}', deptId: '{{ $selectedDeptId ?? '' }}', posId: '{{ $selectedPosId ?? '' }}' },
        deptOptions: @json($departments->values()),
        posOptions:  @json($positions->values()),
        tree:       INITIAL_TREE,
        nodeCount:  0,
        rootCount:  0,
        collapsed:  {}, // node id → bool

        // Transform
        scale:      1,
        tx:         0,
        ty:         0,

        // Drag
        dragging:   false,
        dragStart:  { x: 0, y: 0, tx: 0, ty: 0 },

        // Touch
        lastTouch:  null,

        // Popup
        popup: {
            visible:     false,
            x: 0, y: 0,
            name: '', initials: '', module: '', moduleColor: '',
            department: '', position: '', role: '', roleLabel: '',
            access: '', assignedAt: '', url: '', employeeId: null,
        },

        // ── Init ────────────────────────────────────────────────────────────
        init() {
            this.render();
            // Center initial view
            this.$nextTick(() => this.centerView());
        },

        // ── Filter handlers ─────────────────────────────────────────────────
        async onModuleChange() {
            this.filters.deptId = '';
            this.filters.posId  = '';
            await this.applyFilters();
        },

        async applyFilters() {
            this.loading = true;
            try {
                const p = new URLSearchParams();
                if (this.filters.moduleId) p.set('module_id', this.filters.moduleId);
                if (this.filters.deptId)   p.set('dept_id',   this.filters.deptId);
                if (this.filters.posId)    p.set('pos_id',    this.filters.posId);

                const r = await fetch(`${AJAX_URL}?${p}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await r.json();
                this.tree       = data.tree;
                this.deptOptions = data.departments || [];
                this.posOptions  = data.positions   || [];
            } catch(e) { console.error(e); }
            this.loading = false;
            this.render();
            this.centerView();
        },

        clearFilters() {
            this.filters = { moduleId: '', deptId: '', posId: '' };
            this.applyFilters();
        },

        // ── Tree rendering ──────────────────────────────────────────────────
        render() {
            const nodeEl = document.getElementById('org-nodes');
            const svgEl  = document.getElementById('svg-connections');
            const connG  = document.getElementById('svg-connections').querySelector
                ? document.getElementById('svg-connections')
                : svgEl;
            nodeEl.innerHTML = '';
            connG.innerHTML  = '';

            if (!this.tree || this.tree.length === 0) {
                this.nodeCount = 0;
                this.rootCount = 0;
                this.applyTransform();
                return;
            }

            const positions = {};
            const connectors = [];

            // Layout and count
            let totalNodes = 0;
            const countNodes = (nodes) => {
                nodes.forEach(n => { totalNodes++; if (!this.collapsed[n.id]) countNodes(n.children || []); });
            };
            countNodes(this.tree);

            this.rootCount = this.tree.length;

            // Layout each root tree, distributing horizontally
            let rootX = 24;
            this.tree.forEach(root => {
                const subtreeW = this.subtreeWidth(root);
                this.layoutNode(root, rootX, 24, positions, connectors);
                rootX += subtreeW + H_GAP;
            });

            // Count visible nodes
            let visibleCount = 0;
            Object.keys(positions).forEach(() => visibleCount++);
            this.nodeCount = visibleCount;

            // Render connectors (SVG)
            const svgContent = connectors.map(c => this.renderConnector(c, positions)).join('');
            connG.innerHTML = `<g transform="translate(${this.tx},${this.ty}) scale(${this.scale})">${svgContent}</g>`;

            // Render nodes
            nodeEl.style.transform = `translate(${this.tx}px, ${this.ty}px) scale(${this.scale})`;
            Object.entries(positions).forEach(([id, pos]) => {
                nodeEl.appendChild(this.renderNode(this.findNode(id, this.tree), pos));
            });
        },

        // Calculates width needed for the subtree of a node
        subtreeWidth(node) {
            if (this.collapsed[node.id] || !node.children || node.children.length === 0) {
                return NODE_W;
            }
            const childWidths = node.children.map(c => this.subtreeWidth(c));
            return Math.max(NODE_W, childWidths.reduce((a, b) => a + b + H_GAP, -H_GAP));
        },

        // Layout a node and its children
        layoutNode(node, x, y, positions, connectors, parentId = null) {
            // Center this node over its children
            const subtreeW = this.subtreeWidth(node);
            const nodeX    = x + (subtreeW - NODE_W) / 2;

            positions[node.id] = { x: nodeX, y };

            if (parentId !== null) {
                connectors.push({ from: parentId, to: node.id });
            }

            if (!this.collapsed[node.id] && node.children && node.children.length > 0) {
                let childX = x;
                node.children.forEach(child => {
                    const cw = this.subtreeWidth(child);
                    this.layoutNode(child, childX, y + NODE_H + V_GAP, positions, connectors, node.id);
                    childX += cw + H_GAP;
                });
            }
        },

        renderConnector(c, positions) {
            const from = positions[c.from];
            const to   = positions[c.to];
            if (!from || !to) return '';
            const x1 = from.x + NODE_W / 2;
            const y1 = from.y + NODE_H;
            const x2 = to.x   + NODE_W / 2;
            const y2 = to.y;
            const midY = (y1 + y2) / 2;
            return `<path d="M${x1},${y1} C${x1},${midY} ${x2},${midY} ${x2},${y2}"
                          fill="none" stroke="#E5E7EB" stroke-width="1.5" stroke-dasharray="0"/>`;
        },

        renderNode(node, pos) {
            if (!node) return document.createElement('div');
            const div   = document.createElement('div');
            const color = TYPE_COLORS[node.assignment_type] || '#1B1444';
            const isCollapsed = !!this.collapsed[node.id];
            const hasChildren = node.children && node.children.length > 0;

            div.className   = 'org-node absolute bg-white rounded-xl border-2 shadow-sm select-none cursor-pointer hover:shadow-md transition-shadow';
            div.style.cssText = `width:${NODE_W}px; height:${NODE_H}px; left:${pos.x}px; top:${pos.y}px; border-color: ${color}30;`;
            div.dataset.nodeId = node.id;

            div.innerHTML = `
                <div style="background:${color};" class="rounded-t-[10px] px-3 py-1.5 flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                        ${node.initials}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-white text-xs font-semibold truncate leading-tight">${node.name}</div>
                        <div class="text-white/70 text-[10px] truncate leading-tight">${node.type_label || ''}</div>
                    </div>
                    ${hasChildren ? `
                    <button class="expand-btn w-5 h-5 bg-white/20 hover:bg-white/40 rounded flex items-center justify-center text-white text-[10px] flex-shrink-0 transition-colors"
                            title="${isCollapsed ? 'Expand' : 'Collapse'}">
                        <i class="fas fa-${isCollapsed ? 'plus' : 'minus'}"></i>
                    </button>` : ''}
                </div>
                <div class="px-3 py-2 flex flex-col gap-0.5">
                    <div class="text-[11px] text-gray-700 font-medium truncate">${node.position || node.role || node.role_label || '—'}</div>
                    <div class="text-[10px] text-gray-400 truncate">${node.department ? node.department : (node.module_name || '')}</div>
                    ${hasChildren ? `<div class="text-[9px] text-gray-300 mt-0.5">${node.children.length} direct report${node.children.length > 1 ? 's' : ''}</div>` : ''}
                </div>`;

            // Toggle collapse on expand button
            if (hasChildren) {
                const btn = div.querySelector('.expand-btn');
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggleCollapse(node.id);
                });
            }

            // Show popup on card click
            div.addEventListener('click', (e) => {
                if (e.target.closest('.expand-btn')) return;
                this.showPopup(node, e);
            });

            return div;
        },

        findNode(id, nodes) {
            for (const n of nodes) {
                if (String(n.id) === String(id)) return n;
                const found = this.findNode(id, n.children || []);
                if (found) return found;
            }
            return null;
        },

        toggleCollapse(nodeId) {
            this.collapsed[nodeId] = !this.collapsed[nodeId];
            this.render();
        },

        expandAll() {
            this.collapsed = {};
            this.render();
        },

        collapseAll() {
            const collapse = (nodes) => {
                nodes.forEach(n => {
                    if (n.children && n.children.length) {
                        this.collapsed[n.id] = true;
                        collapse(n.children);
                    }
                });
            };
            collapse(this.tree);
            this.render();
        },

        // ── Popup ────────────────────────────────────────────────────────────
        showPopup(node, event) {
            const rect  = event.target.closest('.org-node').getBoundingClientRect();
            let   px    = rect.right + 8;
            let   py    = rect.top;
            // Keep on screen
            if (px + 264 > window.innerWidth)  px = rect.left - 264 - 8;
            if (py + 280 > window.innerHeight) py = window.innerHeight - 280;

            this.popup = {
                visible:     true,
                x:           px, y: py,
                name:        node.name,
                initials:    node.initials,
                module:      node.module_name,
                moduleColor: node.module_color || '#1B1444',
                department:  node.department,
                position:    node.position,
                role:        node.role,
                roleLabel:   node.role_label,
                access:      node.access_level,
                assignedAt:  node.assigned_at ? `Since ${node.assigned_at}` : '',
                url:         node.employee_url,
                employeeId:  node.id,
            };
        },

        // ── View controls ────────────────────────────────────────────────────
        applyTransform() {
            const nodeEl = document.getElementById('org-nodes');
            const connG  = document.getElementById('svg-connections');
            if (nodeEl) nodeEl.style.transform = `translate(${this.tx}px, ${this.ty}px) scale(${this.scale})`;
            if (connG)  connG.innerHTML = connG.innerHTML; // redraw
            this.render();
        },

        zoom(delta) {
            this.scale = Math.min(2, Math.max(0.2, this.scale + delta));
            this.render();
        },

        onWheel(e) {
            const delta = e.deltaY > 0 ? -0.08 : 0.08;
            this.scale  = Math.min(2, Math.max(0.2, this.scale + delta));
            this.render();
        },

        centerView() {
            const nodeEl = document.getElementById('org-nodes');
            const vp     = document.getElementById('org-viewport');
            if (!nodeEl || !vp) return;
            // Calculate bounding box of all nodes
            const allNodes = nodeEl.querySelectorAll('.org-node');
            if (!allNodes.length) return;
            let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            allNodes.forEach(n => {
                const l = parseFloat(n.style.left), t = parseFloat(n.style.top);
                minX = Math.min(minX, l);
                minY = Math.min(minY, t);
                maxX = Math.max(maxX, l + NODE_W);
                maxY = Math.max(maxY, t + NODE_H);
            });
            const treeW   = maxX - minX;
            const treeH   = maxY - minY;
            const vpW     = vp.clientWidth;
            const vpH     = vp.clientHeight;
            const fitScale = Math.min(1, Math.min((vpW - 48) / treeW, (vpH - 48) / treeH));
            this.scale    = fitScale;
            this.tx       = (vpW - treeW * fitScale) / 2 - minX * fitScale;
            this.ty       = 24;
            this.render();
        },

        resetView() {
            this.scale = 1;
            this.tx    = 0;
            this.ty    = 0;
            this.render();
            this.$nextTick(() => this.centerView());
        },

        // ── Drag ────────────────────────────────────────────────────────────
        startDrag(e) {
            if (e.target.closest('.org-node') || e.target.closest('[data-popup]')) return;
            this.dragging = true;
            this.dragStart = { x: e.clientX, y: e.clientY, tx: this.tx, ty: this.ty };
        },
        doDrag(e) {
            if (!this.dragging) return;
            this.tx = this.dragStart.tx + (e.clientX - this.dragStart.x);
            this.ty = this.dragStart.ty + (e.clientY - this.dragStart.y);
            const nodeEl = document.getElementById('org-nodes');
            const connG  = document.getElementById('svg-connections');
            if (nodeEl) nodeEl.style.transform = `translate(${this.tx}px, ${this.ty}px) scale(${this.scale})`;
            if (connG) {
                const g = connG.querySelector('g');
                if (g) g.setAttribute('transform', `translate(${this.tx},${this.ty}) scale(${this.scale})`);
            }
        },
        endDrag()  { this.dragging = false; },

        // ── Touch ────────────────────────────────────────────────────────────
        touchStart(e) {
            if (e.touches.length === 1) {
                this.lastTouch = { x: e.touches[0].clientX, y: e.touches[0].clientY, tx: this.tx, ty: this.ty };
                this.dragging  = true;
            }
        },
        touchMove(e) {
            if (!this.dragging || !this.lastTouch) return;
            this.tx = this.lastTouch.tx + (e.touches[0].clientX - this.lastTouch.x);
            this.ty = this.lastTouch.ty + (e.touches[0].clientY - this.lastTouch.y);
            const nodeEl = document.getElementById('org-nodes');
            if (nodeEl) nodeEl.style.transform = `translate(${this.tx}px, ${this.ty}px) scale(${this.scale})`;
        },
        touchEnd()  { this.dragging = false; this.lastTouch = null; },
    };
}
</script>
@endsection
