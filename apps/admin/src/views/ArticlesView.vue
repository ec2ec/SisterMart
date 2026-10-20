<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NInputNumber,
  NSpace,
  NSwitch,
  NTag,
  useMessage,
  NPagination,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import WangSimpleEditor from '../components/WangSimpleEditor.vue'

type Article = {
  id: number
  code: string
  status: number
  show_in_footer: boolean
  sort: number
  title: { 'zh-HK': string; en: string }
  body: { 'zh-HK': string; en: string }
}

/** 首頁頁腳三欄固定欄目（與 H5 佈局一致） */
const FOOTER_CODES = [
  { code: 'about', label: '關於我們' },
  { code: 'terms', label: '使用條款' },
  { code: 'shipping', label: '運送政策' },
  { code: 'contact', label: '聯絡我們' },
  { code: 'privacy', label: '私隱政策' },
  { code: 'refund', label: '退款政策' },
  { code: 'service', label: '客戶服務' },
  { code: 'payment', label: '付款政策' },
  { code: 'return', label: '退貨政策' },
] as const

const message = useMessage()
const items = ref<Article[]>([])
const saving = ref(false)
const form = ref({
  id: 0,
  code: '',
  status: true,
  show_in_footer: true,
  sort: 0,
  title_zh: '',
  title_en: '',
  body_zh: '',
  body_en: '',
})

const footerRows = computed(() =>
  FOOTER_CODES.map((f) => ({
    ...f,
    article: items.value.find((a) => a.code === f.code) || null,
  })),
)

const otherItems = computed(() =>
  items.value.filter((a) => !FOOTER_CODES.some((f) => f.code === a.code)),
)

const { page: otherPage, pageSize: otherPageSize, total: otherTotal, paged: otherPaged } =
  useClientPager(otherItems, 30)

const formTitle = computed(() => {
  if (!form.value.id) return '新增文章'
  const foot = FOOTER_CODES.find((f) => f.code === form.value.code)
  return foot ? `編輯頁腳 · ${foot.label}` : `編輯 #${form.value.id}`
})

const codeLocked = computed(() => FOOTER_CODES.some((f) => f.code === form.value.code && form.value.id > 0))

async function load() {
  const data = await api<{ items: Article[] }>('/api/v1/admin/articles', {}, true)
  items.value = data.items
}

function resetForm() {
  form.value = {
    id: 0,
    code: '',
    status: true,
    show_in_footer: false,
    sort: 0,
    title_zh: '',
    title_en: '',
    body_zh: '',
    body_en: '',
  }
}

function edit(row: Article) {
  form.value = {
    id: row.id,
    code: row.code,
    status: row.status === 1,
    show_in_footer: row.show_in_footer,
    sort: row.sort,
    title_zh: row.title['zh-HK'] || '',
    title_en: row.title.en || '',
    body_zh: row.body['zh-HK'] || '',
    body_en: row.body.en || '',
  }
}

function editFooter(code: string, label: string) {
  const row = items.value.find((a) => a.code === code)
  if (row) {
    edit(row)
    return
  }
  form.value = {
    id: 0,
    code,
    status: true,
    show_in_footer: true,
    sort: FOOTER_CODES.findIndex((f) => f.code === code) * 10 + 10,
    title_zh: label,
    title_en: label,
    body_zh: '',
    body_en: '',
  }
}

async function save() {
  if (!form.value.code.trim()) {
    message.warning('請填寫文章代碼')
    return
  }
  if (!form.value.title_zh.trim()) {
    message.warning('請填寫繁中標題')
    return
  }
  saving.value = true
  try {
    const body = {
      code: form.value.code.trim(),
      status: form.value.status ? 1 : 0,
      show_in_footer: form.value.show_in_footer,
      sort: form.value.sort,
      title: {
        'zh-HK': form.value.title_zh.trim(),
        en: form.value.title_en.trim() || form.value.title_zh.trim(),
      },
      body: { 'zh-HK': form.value.body_zh, en: form.value.body_en },
    }
    if (form.value.id) {
      await api(`/api/v1/admin/articles/${form.value.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
    } else {
      await api('/api/v1/admin/articles', { method: 'POST', body: JSON.stringify(body) }, true)
    }
    message.success('文章已保存')
    await load()
    const saved = items.value.find((a) => a.code === body.code)
    if (saved) edit(saved)
  } catch (e: any) {
    message.error(e?.message || '保存失敗')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="頁腳欄目（首頁底部）">
      <p class="hint">
        對應首頁三欄：關於我們／使用條款／運送政策 · 聯絡我們／私隱政策／退款政策 · 客戶服務／付款政策／退貨政策
      </p>
      <div class="footer-grid">
        <div v-for="row in footerRows" :key="row.code" class="footer-cell">
          <div class="footer-meta">
            <strong>{{ row.label }}</strong>
            <code>{{ row.code }}</code>
            <n-tag v-if="row.article" size="small" :type="row.article.status === 1 ? 'success' : 'default'">
              {{ row.article.status === 1 ? '已發布' : '未發布' }}
            </n-tag>
            <n-tag v-else size="small" type="warning">未建檔</n-tag>
          </div>
          <n-button size="small" type="primary" @click="editFooter(row.code, row.label)">編輯</n-button>
        </div>
      </div>
    </n-card>

    <n-card :title="formTitle">
      <n-form-item label="代碼">
        <n-input v-model:value="form.code" :disabled="codeLocked" placeholder="如 about、terms" />
      </n-form-item>
      <n-form-item label="繁中標題"><n-input v-model:value="form.title_zh" /></n-form-item>
      <n-form-item label="英文標題"><n-input v-model:value="form.title_en" /></n-form-item>
      <n-form-item label="繁中正文">
        <WangSimpleEditor v-model="form.body_zh" preset="product" placeholder="繁體中文正文" :height="280" />
      </n-form-item>
      <n-form-item label="英文正文">
        <WangSimpleEditor v-model="form.body_en" preset="product" placeholder="English body" :height="240" />
      </n-form-item>
      <n-space>
        <n-form-item label="排序"><n-input-number v-model:value="form.sort" /></n-form-item>
        <n-form-item label="發布"><n-switch v-model:value="form.status" /></n-form-item>
        <n-form-item label="頁腳展示"><n-switch v-model:value="form.show_in_footer" /></n-form-item>
      </n-space>
      <n-space>
        <n-button type="primary" :loading="saving" @click="save">保存</n-button>
        <n-button @click="resetForm">新建空白</n-button>
      </n-space>
    </n-card>

    <n-card title="其他文章">
      <div v-for="row in otherPaged" :key="row.id" class="row">
        <span>{{ row.code }} · {{ row.title['zh-HK'] }}</span>
        <n-button size="tiny" @click="edit(row)">編輯</n-button>
      </div>
      <p v-if="!otherItems.length" class="hint">暫無其他文章</p>
      <div class="pager">
        <n-pagination
          v-model:page="otherPage"
          v-model:page-size="otherPageSize"
          :item-count="otherTotal"
          :page-sizes="[30, 50, 100]"
          show-size-picker
        />
      </div>
    </n-card>
  </n-space>
</template>

<style scoped>
.hint {
  margin: 0 0 0.75rem;
  font-size: 0.8125rem;
  color: #6d675f;
}
.footer-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
}
.footer-cell {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.65rem 0.75rem;
  border: 1px solid #eee;
  border-radius: 0.375rem;
  background: #fafafa;
}
.footer-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem 0.5rem;
  min-width: 0;
}
.footer-meta code {
  font-size: 0.75rem;
  color: #888;
}
.row {
  display: flex;
  justify-content: space-between;
  padding: 0.4rem 0;
  border-bottom: 1px solid #eee;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
@media (max-width: 900px) {
  .footer-grid {
    grid-template-columns: 1fr;
  }
}
</style>
