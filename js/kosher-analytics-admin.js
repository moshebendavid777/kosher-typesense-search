/**
 * File: kosher-analytics-admin.js
 * Description: Renders lightweight search analytics charts in the WordPress admin dashboard.
 * Author: Kosher Dev Team
 */

(function () {
  'use strict';

  const data = window.kosherSearchAnalyticsData || {};

  function drawAxes(context, width, height) {
    context.strokeStyle = '#e6e8ec';
    context.lineWidth = 1;
    context.beginPath();
    context.moveTo(36, 10);
    context.lineTo(36, height - 28);
    context.lineTo(width - 10, height - 28);
    context.stroke();
  }

  function setupCanvas(canvas) {
    const ratio = window.devicePixelRatio || 1;
    const width = canvas.clientWidth || canvas.parentElement.clientWidth || 480;
    const height = parseInt(canvas.getAttribute('height'), 10) || 260;

    canvas.width = width * ratio;
    canvas.height = height * ratio;
    canvas.style.height = `${height}px`;

    const context = canvas.getContext('2d');
    context.scale(ratio, ratio);
    context.clearRect(0, 0, width, height);

    return { context, width, height };
  }

  function drawEmpty(context, width, height) {
    context.fillStyle = '#667085';
    context.font = '13px Inter, sans-serif';
    context.fillText('No analytics yet', 36, height / 2);
  }

  function drawLineChart(canvas, rows) {
    if (!canvas) {
      return;
    }

    const { context, width, height } = setupCanvas(canvas);
    const values = rows.map((row) => Number(row.total || 0));

    drawAxes(context, width, height);

    if (!values.length) {
      drawEmpty(context, width, height);
      return;
    }

    const max = Math.max(...values, 1);
    const xStep = values.length > 1 ? (width - 56) / (values.length - 1) : width - 56;

    context.strokeStyle = '#7a147a';
    context.lineWidth = 3;
    context.beginPath();

    values.forEach((value, index) => {
      const x = 36 + (index * xStep);
      const y = (height - 28) - ((value / max) * (height - 48));

      if (index === 0) {
        context.moveTo(x, y);
      } else {
        context.lineTo(x, y);
      }
    });

    context.stroke();

    context.fillStyle = '#7a147a';
    values.forEach((value, index) => {
      const x = 36 + (index * xStep);
      const y = (height - 28) - ((value / max) * (height - 48));
      context.beginPath();
      context.arc(x, y, 4, 0, Math.PI * 2);
      context.fill();
    });
  }

  function drawBarChart(canvas, rows) {
    if (!canvas) {
      return;
    }

    const { context, width, height } = setupCanvas(canvas);
    const values = rows.map((row) => Number(row.total || 0));

    drawAxes(context, width, height);

    if (!values.length) {
      drawEmpty(context, width, height);
      return;
    }

    const max = Math.max(...values, 1);
    const barArea = width - 56;
    const barWidth = Math.max(12, Math.min(34, barArea / values.length - 8));

    context.fillStyle = '#7a147a';

    values.forEach((value, index) => {
      const x = 42 + index * (barArea / values.length);
      const barHeight = (value / max) * (height - 54);
      const y = (height - 28) - barHeight;

      context.fillRect(x, y, barWidth, barHeight);
    });
  }

  function renderCharts() {
    drawLineChart(document.getElementById('kosher-search-trend'), data.trend || []);
    drawBarChart(document.getElementById('kosher-top-queries'), data.top_queries || []);
  }

  window.addEventListener('resize', renderCharts);
  document.addEventListener('DOMContentLoaded', renderCharts);
}());
