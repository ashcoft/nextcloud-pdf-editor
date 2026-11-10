<template>
  <div class="pdf-start">
    <header class="pdf-start__header">
      <div class="pdf-start__intro">
        <h1 class="pdf-start__title">{{ appTranslation('PDF Editor') }}</h1>
        <p class="pdf-start__subtitle">
          {{ appTranslation('Open a recent PDF, or browse your files to read and edit a document.') }}
        </p>
      </div>
      <div class="pdf-start__actions">
        <a class="button primary" :href="filesUrl">{{ appTranslation('Browse files') }}</a>
      </div>
    </header>

    <section v-if="recent.length > 0" class="pdf-start__section">
      <h2 class="pdf-start__heading">{{ appTranslation('Recent PDFs') }}</h2>
      <ul class="pdf-start__grid">
        <li v-for="document in recent" :key="document.id">
          <a class="pdf-start__card" :href="editorUrl(document.id)" :title="document.path">
            <svg
              class="pdf-start__icon"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="currentColor"
              aria-hidden="true"
            >
              <path d="M6 2h8l6 6v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1zm7 1.5V9h5.5L13 3.5zM7.5 12h9v1.6h-9V12zm0 3.2h9v1.6h-9v-1.6zm0 3.2h6v1.6h-6v-1.6z" />
            </svg>
            <span class="pdf-start__card-body">
              <span class="pdf-start__name">{{ document.name }}</span>
              <span class="pdf-start__path">{{ folderOf(document.path) }}</span>
              <span class="pdf-start__meta">
                <span class="pdf-start__badge">{{ document.extension.toUpperCase() }}</span>
                {{ formatMtime(document.mtime) }}
              </span>
            </span>
          </a>
        </li>
      </ul>
    </section>

    <section v-else class="pdf-start__empty">
      <h2 class="pdf-start__heading">{{ appTranslation('Recent PDFs') }}</h2>
      <p>{{ appTranslation('No PDFs yet.') }}</p>
      <p class="pdf-start__hint">
        {{ appTranslation('Upload a PDF in Files, then open it here.') }}
      </p>
    </section>
  </div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import { translate } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { buildEditorUrl } from './fileActions'
import type { RecentDocument } from './types/recentDocument'

const appTranslation = (text: string): string => translate('pdf_editor', text)

function editorUrl(fileId: number): string {
  return buildEditorUrl(fileId)
}

function formatMtime(mtime: number): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(mtime * 1000))
}

export default defineComponent({
  name: 'PdfStartPage',
  props: {
    recent: {
      type: Array as PropType<RecentDocument[]>,
      default: () => [],
    },
  },
  setup() {
    const filesUrl = generateUrl('/apps/files/')

    function folderOf(path: string): string {
      const separator = path.lastIndexOf('/')
      return separator > 0 ? path.slice(0, separator) : appTranslation('Home')
    }

    return {
      filesUrl,
      editorUrl,
      folderOf,
      formatMtime,
      appTranslation,
    }
  },
})
</script>

<style scoped>
.pdf-start {
  box-sizing: border-box;
  max-width: 1100px;
  min-height: 100%;
  margin: 0 auto;
  padding: 2.5rem 1.5rem 4rem;
  color: var(--color-main-text);
}

.pdf-start__header {
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 2.5rem;
}

.pdf-start__title {
  margin: 0 0 0.35rem;
  font-size: 2rem;
  font-weight: 700;
}

.pdf-start__subtitle {
  margin: 0;
  color: var(--color-text-maxcontrast);
}

.pdf-start__actions .button {
  margin: 0;
}

.pdf-start__heading {
  margin: 0 0 1rem;
  font-size: 1.15rem;
  font-weight: 600;
}

.pdf-start__grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  margin: 0;
  padding: 0;
  list-style: none;
}

.pdf-start__card {
  display: flex;
  gap: 0.85rem;
  align-items: flex-start;
  box-sizing: border-box;
  height: 100%;
  padding: 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius-large, 12px);
  background: var(--color-main-background);
  color: inherit;
  text-decoration: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.pdf-start__card:hover,
.pdf-start__card:focus-visible {
  border-color: var(--color-primary-element);
  box-shadow: 0 2px 8px rgb(0 0 0 / 15%);
  text-decoration: none;
}

.pdf-start__icon {
  flex: 0 0 auto;
  width: 32px;
  height: 32px;
  color: var(--color-primary-element);
}

.pdf-start__card-body {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}

.pdf-start__name {
  overflow: hidden;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pdf-start__path {
  overflow: hidden;
  color: var(--color-text-maxcontrast);
  font-size: 0.85rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pdf-start__meta {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin-top: 0.2rem;
  color: var(--color-text-maxcontrast);
  font-size: 0.8rem;
}

.pdf-start__badge {
  padding: 0.1rem 0.4rem;
  border-radius: var(--border-radius, 4px);
  background: var(--color-background-dark);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.pdf-start__empty {
  padding: 3rem 1.5rem;
  border: 1px dashed var(--color-border);
  border-radius: var(--border-radius-large, 12px);
  text-align: center;
}

.pdf-start__hint {
  color: var(--color-text-maxcontrast);
  font-size: 0.9rem;
}
</style>
