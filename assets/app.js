// Entry point of the importmap (AssetMapper, no Node build step).
// Mermaid renders <pre class="mermaid"> blocks, highlight.js colours <pre><code class="language-x">,
// Chart.js draws the small statistics on the chronicle page.
import './styles/app.css';
import mermaid from 'mermaid';
import hljs from 'highlight.js';
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

mermaid.initialize({
    startOnLoad: true,
    theme: prefersDark ? 'dark' : 'default',
    securityLevel: 'loose',
});

hljs.highlightAll();

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

for (const canvas of document.querySelectorAll('canvas[data-chart="bar"]')) {
    const labels = JSON.parse(canvas.dataset.labels ?? '[]');
    const values = JSON.parse(canvas.dataset.values ?? '[]');
    const text = getComputedStyle(document.documentElement).getPropertyValue('--color-muted').trim() || '#666';

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{ data: values, backgroundColor: '#1d4ed8', borderRadius: 3 }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: text }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: text, precision: 0 }, grid: { color: 'rgba(128,128,128,.2)' } },
            },
        },
    });
}
