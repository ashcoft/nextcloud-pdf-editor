<template>
  <div id="pdf-editor-wrapper" class="pdf-editor-wrapper" :class="`pdf-editor-wrapper--${theme}`">
    <header class="pdf-editor-toolbar">
      <div class="pdf-editor-toolbar__group">
        <span class="pdf-editor-toolbar__file">{{ fileName }}</span>
        <span v-if="dirty" class="pdf-editor-toolbar__dirty" role="status">
          {{ appTranslation('Unsaved changes') }}
        </span>
      </div>

      <div class="pdf-editor-toolbar__group pdf-editor-toolbar__group--end">
        <span v-if="saving" class="pdf-editor-toolbar__status" role="status">
          {{ appTranslation('Saving…') }}
        </span>
        <button
          type="button"
          class="button"
          :title="appTranslation('Close the editor and return to Files')"
          @click="exitEditor"
        >
          {{ appTranslation('Exit') }}
        </button>
      </div>
    </header>

    <div class="pdf-editor-body">
      <output v-if="status === 'loading'" class="pdf-editor-status">
        {{ appTranslation('Loading the editor…') }}
      </output>
      <p v-else-if="status === 'error'" class="pdf-editor-status pdf-editor-status--error" role="alert">
        {{ errorMessage }}
      </p>
      <output v-if="statusMessage" class="pdf-editor-status">{{ statusMessage }}</output>

      <!--
        The editor fills the frame and is kept mounted once loaded: remounting
        it would discard the user's document, so the toolbar and status overlay
        it instead.
      -->
      <iframe
        ref="frameElement"
        class="pdf-editor-frame"
        :class="{ 'pdf-editor-frame--hidden': status !== 'ready' }"
        :src="editorUrl"
        title="PdfCraft"
        allow="fullscreen; clipboard-read; clipboard-write"
        allowfullscreen
      ></iframe>
    </div>
  </div>
</template>

<script lang="ts">
import { computed, defineComponent, onBeforeUnmount, onMounted, ref } from 'vue'
import { translate } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { PdfCraftBridge, type BridgeOutput } from './editor/pdfcraftBridge'
import { fetchFile } from './utils/fetchFile'
import { saveFile } from './utils/saveFile'
import { toDataUrl } from './utils/dataUrl'

const appTranslation = (text: string): string => translate('pdf_editor', text)

export default defineComponent({
  name: 'PdfEditorApp',

  props: {
    fileId: {
      type: String as () => string | null,
      default: null,
    },
  },

  setup(props) {
    const frameElement = ref<HTMLIFrameElement | null>(null)
    const status = ref<'loading' | 'ready' | 'error'>('loading')
    const errorMessage = ref('')
    const statusMessage = ref('')
    const saving = ref(false)
    const dirty = ref(false)
    const canSave = ref(false)
    const fileName = ref('')
    const theme = ref<'light' | 'dark'>('light')

    let etag = ''
    let fileId: number | null = null
    let bridge: PdfCraftBridge | null = null

    // The bundle is served by the app, so the frame stays same-origin and the
    // bridge can talk to it with postMessage. `?host=parent` switches PdfCraft
    // to its host contract instead of its own download path.
    const editorUrl = computed(
      () => `${generateUrl('/apps/pdf_editor/pdfcraft/index.html')}?host=parent`,
    )

    /**
     * Store a document the editor saved.
     *
     * The save carries the etag the document was loaded from, so the server
     * can refuse it when the file changed underneath. A successful save
     * returns the new version, which the next save uses.
     */
    async function onSave(output: BridgeOutput): Promise<boolean> {
      if (fileId === null) {
        return false
      }
      if (!canSave.value) {
        statusMessage.value = appTranslation('This file cannot be saved.')
        return false
      }

      saving.value = true
      statusMessage.value = appTranslation('Saving…')

      try {
        const result = await saveFile(fileId, etag, toDataUrl(output.bytes))

        if (!result.ok) {
          statusMessage.value = result.error ?? appTranslation('Could not save the file.')
          return false
        }

        etag = result.etag ?? etag
        dirty.value = false
        statusMessage.value = appTranslation('Saved.')
        return true
      } catch {
        statusMessage.value = appTranslation('Could not save the file.')
        return false
      } finally {
        saving.value = false
      }
    }

    /**
     * Connect the bridge and hand the editor the file.
     *
     * Run on mount, before the iframe has loaded: PdfCraft announces itself
     * with `ready` while its own boot is still running, so a listener added
     * from the frame's `load` event would miss the announcement and the editor
     * would sit idle with no document.
     */
    async function start(): Promise<void> {
      const frame = frameElement.value
      if (frame === null || props.fileId === null || bridge !== null) {
        return
      }

      try {
        bridge = new PdfCraftBridge(frame, {
          onSave,
          onDirty: (value) => {
            dirty.value = value
          },
          onFailed: (error) => {
            errorMessage.value = error
          },
        })

        await bridge.ready()

        const result = await fetchFile(props.fileId)
        if (!result.ok) {
          status.value = 'error'
          errorMessage.value = result.error ?? appTranslation('Could not open this file.')
          return
        }

        fileId = result.meta.id
        etag = result.meta.etag
        canSave.value = result.meta.canSave
        fileName.value = result.meta.name
        theme.value = result.meta.theme

        bridge.open(result.meta.name, result.bytes)
        status.value = 'ready'
      } catch (error) {
        status.value = 'error'
        errorMessage.value = error instanceof Error
          ? error.message
          : appTranslation('Could not start the editor.')
      }
    }

    onMounted(() => {
      void start()
    })

    /**
     * Leave the editor for the Files app.
     *
     * A save in flight is refused first: navigating away would abort the
     * request and the change would be lost.
     */
    function exitEditor(): void {
      if (saving.value) {
        statusMessage.value = appTranslation('Saving…')
        return
      }

      if (dirty.value && !window.confirm(appTranslation('You have unsaved changes. Leave anyway?'))) {
        return
      }

      window.location.href = generateUrl('/apps/files')
    }

    onBeforeUnmount(() => {
      bridge?.dispose()
    })

    return {
      appTranslation,
      frameElement,
      editorUrl,
      status,
      errorMessage,
      statusMessage,
      saving,
      dirty,
      fileName,
      theme,
      exitEditor,
    }
  },
})
</script>

<style scoped>
.pdf-editor-wrapper {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

.pdf-editor-body {
  position: relative;
  flex: 1 1 auto;
  min-height: 0;
}

.pdf-editor-frame {
  display: block;
  width: 100%;
  height: 100%;
  border: 0;
}

.pdf-editor-frame--hidden {
  visibility: hidden;
}

.pdf-editor-status {
  position: absolute;
  inset: auto 0 0;
  margin: 0;
  padding: 0.5rem 1rem;
  text-align: center;
}

.pdf-editor-status--error {
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pdf-editor-toolbar__file {
  font-weight: 600;
  padding-inline: 0.5rem;
}

.pdf-editor-toolbar__dirty {
  padding-inline: 0.5rem;
  color: var(--color-text-maxcontrast);
  font-size: 0.85rem;
}
</style>
