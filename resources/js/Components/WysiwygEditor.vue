<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import { watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    disabled:   { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    editable: !props.disabled,
    extensions: [
        StarterKit,
        Underline,
        Link.configure({ openOnClick: false }),
    ],
    onUpdate({ editor }) {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val, false);
    }
});

watch(() => props.disabled, (val) => {
    editor.value?.setEditable(!val);
});

function setLink() {
    const url = window.prompt('URL odkazu:', editor.value?.getAttributes('link').href ?? '');
    if (url === null) return;
    if (url === '') {
        editor.value?.chain().focus().unsetLink().run();
    } else {
        editor.value?.chain().focus().setLink({ href: url }).run();
    }
}
</script>

<template>
    <div :class="['rounded-lg border overflow-hidden', disabled ? 'border-gray-200 bg-gray-50' : 'border-gray-200 bg-white focus-within:border-indigo-400 focus-within:ring-1 focus-within:ring-indigo-400']">

        <!-- Toolbar -->
        <div v-if="!disabled" class="flex flex-wrap items-center gap-0.5 border-b border-gray-100 bg-gray-50 px-2 py-1.5">

            <!-- Bold -->
            <button type="button" @click="editor?.chain().focus().toggleBold().run()"
                    :class="['toolbar-btn', editor?.isActive('bold') && 'toolbar-btn-active']" title="Tučné">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z"/></svg>
            </button>

            <!-- Italic -->
            <button type="button" @click="editor?.chain().focus().toggleItalic().run()"
                    :class="['toolbar-btn', editor?.isActive('italic') && 'toolbar-btn-active']" title="Kurzíva">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z"/></svg>
            </button>

            <!-- Underline -->
            <button type="button" @click="editor?.chain().focus().toggleUnderline().run()"
                    :class="['toolbar-btn', editor?.isActive('underline') && 'toolbar-btn-active']" title="Podčiarknuté">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17c3.31 0 6-2.69 6-6V3h-2.5v8c0 1.93-1.57 3.5-3.5 3.5S8.5 12.93 8.5 11V3H6v8c0 3.31 2.69 6 6 6zm-7 2v2h14v-2H5z"/></svg>
            </button>

            <div class="mx-1 h-5 w-px bg-gray-200"/>

            <!-- H2 -->
            <button type="button" @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
                    :class="['toolbar-btn text-xs font-bold', editor?.isActive('heading', { level: 2 }) && 'toolbar-btn-active']" title="Nadpis 2">
                H2
            </button>

            <!-- H3 -->
            <button type="button" @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
                    :class="['toolbar-btn text-xs font-bold', editor?.isActive('heading', { level: 3 }) && 'toolbar-btn-active']" title="Nadpis 3">
                H3
            </button>

            <div class="mx-1 h-5 w-px bg-gray-200"/>

            <!-- Bullet list -->
            <button type="button" @click="editor?.chain().focus().toggleBulletList().run()"
                    :class="['toolbar-btn', editor?.isActive('bulletList') && 'toolbar-btn-active']" title="Zoznam">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg>
            </button>

            <!-- Ordered list -->
            <button type="button" @click="editor?.chain().focus().toggleOrderedList().run()"
                    :class="['toolbar-btn', editor?.isActive('orderedList') && 'toolbar-btn-active']" title="Číslovaný zoznam">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z"/></svg>
            </button>

            <div class="mx-1 h-5 w-px bg-gray-200"/>

            <!-- Link -->
            <button type="button" @click="setLink"
                    :class="['toolbar-btn', editor?.isActive('link') && 'toolbar-btn-active']" title="Odkaz">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
            </button>

            <!-- Clear formatting -->
            <button type="button" @click="editor?.chain().focus().clearNodes().unsetAllMarks().run()"
                    class="toolbar-btn ml-auto" title="Odstrániť formátovanie">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4v2h4v-2h4v-2H6v2zM17.71 6.63L16 5l-8 8-1.71 1.71c-.39.39-.39 1.02 0 1.41l1.41 1.41c.39.39 1.02.39 1.41 0L10 16l1 1h2l4.71-4.71c1.17-1.17 1.17-3.07 0-4.24l-.96-.96c-.78-.79-1.8-1.18-2.82-1.18z"/></svg>
            </button>
        </div>

        <!-- Editor content -->
        <EditorContent
            :editor="editor"
            class="prose prose-sm max-w-none px-3 py-2 text-sm focus:outline-none min-h-[180px]"
        />
    </div>
</template>

<style scoped>
.toolbar-btn {
    @apply rounded p-1.5 text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition-colors;
}
.toolbar-btn-active {
    @apply bg-indigo-100 text-indigo-700;
}
:deep(.ProseMirror) {
    @apply outline-none min-h-[160px];
}
:deep(.ProseMirror p) { @apply mb-2; }
:deep(.ProseMirror h2) { @apply text-lg font-bold mb-2 mt-3; }
:deep(.ProseMirror h3) { @apply text-base font-semibold mb-1 mt-2; }
:deep(.ProseMirror ul) { @apply list-disc pl-5 mb-2; }
:deep(.ProseMirror ol) { @apply list-decimal pl-5 mb-2; }
:deep(.ProseMirror a) { @apply text-indigo-600 underline; }
:deep(.ProseMirror strong) { @apply font-bold; }
:deep(.ProseMirror em) { @apply italic; }
:deep(.ProseMirror u) { @apply underline; }
</style>
