<template>
    <div class="board-wrapper" :tabindex="-1" @touchend="fixActionRestriction">
        <Controls :board="board" />

        <div class="board-scroll-container">
            
            <div v-if="board" class="saas-section">
                <div class="saas-section-header clickable" @click="toggleSection('timeline')">
                    <h3>{{ t('deck', 'Timeline') }}</h3>
                    <ChevronDown class="section-icon" :class="{ 'is-collapsed': sectionState.timeline }" />
                </div>
                <div v-show="!sectionState.timeline" class="section-content">
                    <TimelineWidget :board-id="board.id" />
                </div>
            </div>

            <div v-if="board" class="saas-section">
                <div class="saas-section-header clickable" @click="toggleSection('analytics')">
                    <h3>{{ t('deck', 'Analytics') }}</h3>
                    <ChevronDown class="section-icon" :class="{ 'is-collapsed': sectionState.analytics }" />
                </div>
                <div v-show="!sectionState.analytics">
                    <ReportingDashboard />
                </div>
            </div>

            <transition name="fade" mode="out-in">
                <div v-if="loading" key="loading" class="emptycontent">
                    <div class="icon icon-loading" />
                    <h2>{{ t('deck', 'Loading board') }}</h2>
                </div>

                <div v-else-if="!board" key="notfound" class="emptycontent">
                    <div class="icon icon-deck" />
                    <h2>{{ t('deck', 'Board not found') }}</h2>
                </div>

                <NcEmptyContent v-else-if="isEmpty" key="empty">
                    <template #icon><DeckIcon /></template>
                    <template #name>{{ t('deck', 'No lists available') }}</template>
                    <template v-if="canManage" #action>
                        {{ t('deck', 'Create a new list to add cards to this board') }}
                        <form @submit.prevent="addNewStack()">
                            <NcTextField ref="newStackInput"
                                :disable="loading"
                                :value.sync="newStackTitle"
                                :placeholder="t('deck', 'List name')"
                                type="text" />
                            <NcButton type="secondary"
                                native-type="submit"
                                :disabled="loading"
                                :title="t('deck', 'Add list')">
                                <template #icon>
                                    <CheckIcon v-if="!loading" :size="20" />
                                    <NcLoadingIcon v-else :size="20" />
                                </template>
                                {{ t('deck', 'Add list') }}
                            </NcButton>
                        </form>
                    </template>
                </NcEmptyContent>

                <div v-else-if="!isEmpty && !loading"
                    key="board"
                    class="saas-section board-section">
                    
                    <div class="saas-section-header clickable" @click="toggleSection('tasks')">
                        <h3>{{ t('deck', 'Tasks') }}</h3>
                        <ChevronDown class="section-icon" :class="{ 'is-collapsed': sectionState.tasks }" />
                    </div>

                    <div v-show="!sectionState.tasks" ref="board" class="board" @mousedown="onMouseDown">
                        <Container lock-axis="y"
                            orientation="horizontal"
                            :drag-handle-selector="dragHandleSelector"
                            data-click-closes-sidebar="true"
                            @drag-start="draggingStack = true"
                            @drag-end="draggingStack = false"
                            @drop="onDropStack">
                            <Draggable v-for="stack in stacksByBoard"
                                :key="stack.id"
                                data-click-closes-sidebar="true"
                                data-dragscroll-enabled
                                class="stack-draggable-wrapper">
                                <Stack :stack="stack" :dragging="draggingStack" data-click-closes-sidebar="true" />
                            </Draggable>
                        </Container>
                    </div>
                </div>
            </transition>
        </div>

        <GlobalSearchResults v-if="isFullApp" />
        <NcModal v-if="localModal"
            :clear-view-delay="0"
            :close-button-contained="true"
            size="large"
            @close="localModal = null">
            <div class="modal__content modal__card">
                <CardSidebar :id="localModal" @close="localModal = null" />
            </div>
        </NcModal>
    </div>
</template>

<script>
import { Container, Draggable } from 'vue-smooth-dnd'
import { mapState, mapGetters } from 'vuex'
import Controls from '../Controls.vue'
import DeckIcon from '../icons/DeckIcon.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue' 
import Stack from './Stack.vue'
import { NcEmptyContent, NcModal, NcButton, NcTextField, NcLoadingIcon } from '@nextcloud/vue'
import GlobalSearchResults from '../search/GlobalSearchResults.vue'
import { showError } from '../../helpers/errors.js'
import { createSession } from '../../sessions.js'
import CardSidebar from '../card/CardSidebar.vue'
import TimelineWidget from './TimelineWidget.vue'
import ReportingDashboard from './ReportingDashboard.vue'

export default {
    name: 'Board',
    components: {
        GlobalSearchResults,
        Controls,
        Container,
        DeckIcon,
        Draggable,
        Stack,
        NcEmptyContent,
        NcModal,
        NcTextField,
        NcButton,
        NcLoadingIcon,
        CheckIcon,
        ChevronDown, 
        CardSidebar,
        TimelineWidget,
        ReportingDashboard,
    },
    inject: ['boardApi'],
    props: {
        id: { type: Number, default: null },
    },
    data() {
        return {
            draggingStack: false,
            loading: true,
            newStackTitle: '',
            currentScrollPosX: null,
            currentMousePosX: null,
            localModal: null,
            sectionState: {
                timeline: false,
                analytics: false,
                tasks: false
            }
        }
    },
    computed: {
        ...mapState({
            isFullApp: state => state.isFullApp,
            board: state => state.currentBoard,
            showArchived: state => state.showArchived,
        }),
        ...mapGetters(['canEdit', 'canManage']),
        stacksByBoard() {
            return this.board?.id ? this.$store.getters.stacksByBoard(this.board.id) : []
        },
        dragHandleSelector() {
            return this.canEdit ? '.stack__title' : '.no-drag'
        },
        isEmpty() {
            return this.stacksByBoard.length === 0
        },
    },
    watch: {
        id() { this.fetchData() },
        showArchived() { this.fetchData() },
        isEmpty(newValue) {
            newValue && this.$nextTick(() => {
                this.$refs?.newStackInput?.focus()
            })
        },
    },
    created() {
        this.session = createSession(this.id)
        this.fetchData()
        this.$root.$on('open-card', (cardId) => {
            this.localModal = cardId
        })
    },
    beforeDestroy() {
        this.session.close()
    },
    methods: {
        toggleSection(section) {
            this.sectionState[section] = !this.sectionState[section]
        },
        async fetchData() {
            this.loading = true
            try {
                await this.$store.dispatch('loadBoardById', this.id)
                await this.$store.dispatch('loadStacks', this.id)
                const routeCardId = parseInt(this.$route.params.cardId)
                if (routeCardId && !this.$store.getters.cardById(routeCardId)) {
                    await this.$store.dispatch('loadArchivedStacks', this.id)
                    if (this.$store.getters.cardById(routeCardId)) {
                        this.$store.commit('toggleShowArchived', true)
                    }
                }
                this.session?.close()
                this.session = createSession(this.id)
            } catch (e) {
                console.error(e)
                showError(e)
            } finally {
                this.loading = false
            }
        },
        onDropStack({ removedIndex, addedIndex }) {
            this.$store.dispatch('orderStack', { stack: this.stacksByBoard[removedIndex], removedIndex, addedIndex })
        },
        addNewStack() {
            const newStack = { title: this.newStackTitle, boardId: this.id }
            this.$store.dispatch('createStack', newStack)
            this.newStackTitle = ''
        },
        onMouseDown(event) {
            this.startMouseDrag(event)
        },
        startMouseDrag(event) {
            if (!('dragscrollEnabled' in event.target.dataset)) return
            event.preventDefault()
            this.currentMousePosX = event.clientX
            this.currentScrollPosX = this.$refs.board.scrollLeft
            window.addEventListener('mousemove', this.handleMouseDrag)
            window.addEventListener('mouseup', this.stopMouseDrag)
            window.addEventListener('mouseleave', this.stopMouseDrag)
        },
        handleMouseDrag(event) {
            event.preventDefault()
            const deltaX = event.clientX - this.currentMousePosX
            this.$refs.board.scrollLeft = this.currentScrollPosX - deltaX
        },
        stopMouseDrag(event) {
            window.removeEventListener('mousemove', this.handleMouseDrag)
            window.removeEventListener('mouseup', this.stopMouseDrag)
            window.removeEventListener('mouseleave', this.stopMouseDrag)
        },
        fixActionRestriction() {
            document.body.classList.remove('smooth-dnd-no-user-select', 'smooth-dnd-disable-touch-action')
        },
    },
}
</script>

<style lang="scss" scoped>
@import '../../css/animations';
@import '../../css/variables';

/* --- SAAS DESIGN TOKENS --- */
$saas-bg: #ffffff;
$saas-border: #e2e8f0;
$saas-text: #0f172a;
$saas-primary: #0082c9;

/* --- MAIN WRAPPER --- */
.board-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    max-height: calc(100vh - 50px);
    display: flex;
    flex-direction: column;
    background-color: $saas-bg;
}

/* --- SCROLL CONTAINER (UPDATED LAYOUT) --- */
.board-scroll-container {
    flex-grow: 1;
    overflow-y: auto;
    overflow-x: hidden;
    
    /* NEW: Flexbox layout for consistent spacing */
    display: flex;
    flex-direction: column;
    /* This creates exactly 24px between all sections */
    gap: 24px; 
    
    /* Top and bottom breathing room */
    padding: 24px 0; 
}

/* --- SECTION STYLING --- */
.saas-section {
    width: 100%;
    /* Reset margins so 'gap' controls the space */
    margin: 0; 
    padding: 0 20px;
    flex-shrink: 0; /* Prevent collapsing */
}

/* --- HEADER STYLING --- */
.saas-section-header {
    /* Reset top margin */
    margin: 0 0 16px 0; 
    padding-left: 12px;
    border-left: 4px solid $saas-primary;
    display: flex;
    align-items: center;
    justify-content: space-between;
    
    &.clickable {
        cursor: pointer;
        user-select: none;
        transition: background-color 0.2s ease;
        padding-right: 8px;

        &:hover {
            opacity: 0.8;
        }
    }

    h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: $saas-text;
        line-height: 1.2;
    }

    .section-icon {
        color: #64748b;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        
        &.is-collapsed {
            transform: rotate(-90deg);
        }
    }
}

/* --- BOARD (KANBAN) SPECIFIC --- */
.board-section {
    /* This ensures the tasks section fills remaining screen space */
    flex-grow: 1; 
    display: flex;
    flex-direction: column;
}

.board {
    position: relative;
    flex-grow: 1;
    min-height: 500px; 
    overflow: hidden;
    overflow-x: auto;
    padding-bottom: 20px;

    &::-webkit-scrollbar { height: 10px; }
    &::-webkit-scrollbar-track { background: transparent; }
    &::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 5px;
        border: 2px solid $saas-bg;
    }
}

/* --- FORM STYLES --- */
form {
    text-align: center;
    display: flex;
    width: 100%;
    max-width: 400px;
    margin: auto;
    margin-top: calc(var(--default-grid-baseline) * 4);
    gap: var(--default-grid-baseline);

    input[type="text"] {
        flex-grow: 1;
        border: 1px solid $saas-border;
        background: $saas-bg;
        border-radius: 6px;
        padding: 8px 12px;
    }
    button[type="submit"] {
        flex-shrink: 0;
    }
}

/* --- DRAG & DROP OVERRIDES (Standard) --- */
.smooth-dnd-container.horizontal {
    display: flex;
    align-items: stretch;
    height: 100%;

    &:deep(.stack-draggable-wrapper.smooth-dnd-draggable-wrapper) {
        display: flex;
        height: auto;
        max-height: none !important;

        .stack {
            display: flex;
            flex-direction: column;
            position: relative;
            background: transparent !important; 
            border: none !important;
            box-shadow: none !important;

            .smooth-dnd-container.vertical {
                flex-grow: 1;
                display: flex;
                flex-direction: column;
                margin-left: $stack-spacing;
                padding-right: $stack-spacing;
                overflow-x: hidden;
                overflow-y: auto;
                padding-top: 15px;
                margin-top: -10px;
                scrollbar-gutter: stable;
                
                &::-webkit-scrollbar { width: 6px; }
                &::-webkit-scrollbar-thumb {
                    background-color: #cbd5e1;
                    border-radius: 3px;
                }
            }
            .smooth-dnd-container.vertical .smooth-dnd-draggable-wrapper {
                height: auto;
                padding-bottom: 8px;
            }
        }
    }
}

/* --- EMPTY STATE --- */
.emptycontent {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    color: #64748b;
    flex-grow: 1; /* Center vertically in empty space */

    h2 {
        font-size: 18px;
        font-weight: 600;
        color: $saas-text;
        margin-top: 16px;
    }
    .icon { opacity: 0.6; }
}
</style>