import { ref, onUnmounted } from 'vue';
import axios from 'axios';

let searchDebounceTimer = null;

export function useSearch() {
  const searchQuery = ref('');
  const searchResults = ref(null);
  const searchLoading = ref(false);
  const searchError = ref(null);
  const searchPage = ref(1);
  const searchHasMore = ref(false);

  function onSearchInput() {
    const q = searchQuery.value.trim();

    if (q === '') {
      cancelSearchDebounce();
      searchResults.value = null;
      searchError.value = null;
      searchPage.value = 1;
      searchHasMore.value = false;
      return;
    }

    if (q.length < 2) {
      cancelSearchDebounce();
      searchResults.value = null;
      searchError.value = null;
      searchPage.value = 1;
      searchHasMore.value = false;
      return;
    }

    cancelSearchDebounce();
    searchDebounceTimer = setTimeout(() => {
      searchPage.value = 1;
      performSearch(q, 1);
    }, 300);
  }

  function searchLoadMore() {
    const q = searchQuery.value.trim();
    if (q.length < 2 || searchLoading.value || !searchHasMore.value) return;
    const nextPage = searchPage.value + 1;
    performSearch(q, nextPage, true);
  }

  async function performSearch(q, page, append = false) {
    searchLoading.value = true;
    searchError.value = null;

    try {
      const { data } = await axios.get('/api/search', {
        params: { q, page, per_page: 15 },
      });

      if (append) {
        searchResults.value = [...searchResults.value, ...data.data];
      } else {
        searchResults.value = data.data;
      }

      searchPage.value = data.meta.current_page;
      searchHasMore.value = data.meta.current_page < data.meta.last_page;
    } catch (e) {
      if (!append) {
        searchResults.value = [];
      }
      searchError.value = e.response?.data?.error?.message || 'Search failed. Please try again.';
    } finally {
      searchLoading.value = false;
    }
  }

  function cancelSearchDebounce() {
    if (searchDebounceTimer) {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = null;
    }
  }

  onUnmounted(() => {
    cancelSearchDebounce();
  });

  return {
    searchQuery,
    searchResults,
    searchLoading,
    searchError,
    searchPage,
    searchHasMore,
    onSearchInput,
    searchLoadMore,
  };
}
