import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  base: '/airlock-php/',
  title: "Airlock",
  description: "British-style queuing for your infrastructure.",
  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config
    nav: [
      { text: 'Guide', link: '/getting-started' },
      { text: 'Reference', link: '/reference/seals' },
      { text: 'Recipes', link: '/recipes' },
    ],

    sidebar: [
      {
        text: 'Introduction',
        items: [
          { text: 'Getting Started', link: '/getting-started' },
          { text: 'Core Concepts', link: '/core-concepts' },
        ]
      },
      {
        text: 'Guide',
        items: [
          { text: 'Strategies', link: '/strategies' },
          { text: 'Recipes', link: '/recipes' },
        ]
      },
      {
        text: 'Reference',
        items: [
          { text: 'Seals', link: '/reference/seals' },
          { text: 'Queues', link: '/reference/queues' },
          { text: 'Decorators & Events', link: '/reference/decorators-and-events' },
          { text: 'Bridges', link: '/reference/bridges' },
        ]
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/clegginabox/airlock-php' }
    ]
  }
})
