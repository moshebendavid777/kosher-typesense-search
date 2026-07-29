/**
 * File: kosher-media-playback-guard.js
 * Description: Prevents overlapping story, video, audio, and embedded media playback.
 * Author: Kosher Dev Team
 */

(function () {
  'use strict';

  const MEDIA_SELECTOR = 'video, audio';
  const EMBED_SELECTOR = 'iframe';
  const STORY_SELECTOR = [
    '.story',
    '.stories',
    '.web-story',
    '.web-stories-list__story',
    'amp-story-player',
    '.amp-story-player'
  ].join(',');
  const INTERACTION_SELECTOR = [
    'a[href]',
    'button',
    '[role="button"]',
    '.kayco-card',
    '.kosher-card',
    '.item-card',
    '.episode-card',
    '.story',
    '.stories',
    '.web-story',
    '.web-stories-list__story',
    'iframe',
    'amp-story-player',
    '.amp-story-player',
    '.swiper-button-next',
    '.swiper-button-prev'
  ].join(',');
  const MEDIA_IFRAME_PATTERN = /(youtube|youtu\.be|vimeo|jwplayer|jwplatform|cloudflarestream|wistia|brightcove|vidyard|player|video|amp-story|webstories|stories|kosher\.com)/i;

  const iframeSources = new WeakMap();
  let lastNativeMedia = null;
  let stopTimer = null;

  function rememberIframeSource(iframe) {
    if (!iframe || iframeSources.has(iframe)) {
      return;
    }

    const src = iframe.getAttribute('src');

    if (src) {
      iframeSources.set(iframe, src);
    }
  }

  function rememberIframeSources(root) {
    if (!root || !root.querySelectorAll) {
      return;
    }

    root.querySelectorAll(EMBED_SELECTOR).forEach(rememberIframeSource);
  }

  function pauseNativeMedia(except) {
    document.querySelectorAll(MEDIA_SELECTOR).forEach((media) => {
      if (media === except) {
        return;
      }

      try {
        media.pause();
        media.removeAttribute('autoplay');
      } catch (error) {
        // Some browser-managed players can throw while their source is changing.
      }
    });
  }

  function pauseEmbeddedPlayer(iframe) {
    if (!iframe || !iframe.contentWindow) {
      return;
    }

    rememberIframeSource(iframe);

    try {
      iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
      iframe.contentWindow.postMessage('{"method":"pause"}', '*');
      iframe.contentWindow.postMessage({ event: 'command', func: 'pauseVideo', args: [] }, '*');
      iframe.contentWindow.postMessage({ method: 'pause' }, '*');
    } catch (error) {
      // Cross-origin embeds are allowed to ignore these messages.
    }
  }

  function isVisible(element) {
    return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
  }

  function resetIframe(iframe) {
    const originalSrc = iframeSources.get(iframe) || iframe.getAttribute('src');

    if (!originalSrc || !MEDIA_IFRAME_PATTERN.test(originalSrc)) {
      return;
    }

    iframe.setAttribute('src', 'about:blank');

    window.setTimeout(() => {
      if (iframe.isConnected) {
        iframe.setAttribute('src', originalSrc);
      }
    }, 50);
  }

  function stopEmbeddedPlayers(options) {
    const settings = Object.assign({
      except: null,
      resetHiddenIframes: false,
      forceReset: false
    }, options);

    document.querySelectorAll(EMBED_SELECTOR).forEach((iframe) => {
      if (iframe === settings.except || iframe.contains(settings.except)) {
        return;
      }

      pauseEmbeddedPlayer(iframe);

      if (settings.forceReset || (settings.resetHiddenIframes && !isVisible(iframe))) {
        resetIframe(iframe);
      }
    });
  }

  function stopBackgroundMedia(options) {
    const settings = Object.assign({
      except: null,
      resetHiddenIframes: false,
      forceReset: false
    }, options);

    pauseNativeMedia(settings.except);
    stopEmbeddedPlayers(settings);
  }

  function scheduleHiddenIframeCleanup() {
    window.clearTimeout(stopTimer);

    stopTimer = window.setTimeout(() => {
      stopBackgroundMedia({ resetHiddenIframes: true });
    }, 150);
  }

  function isMediaControlClick(target) {
    return !!(target && target.closest && target.closest(MEDIA_SELECTOR));
  }

  document.addEventListener('DOMContentLoaded', () => {
    rememberIframeSources(document);
  });

  document.addEventListener('play', (event) => {
    if (!event.target || !event.target.matches || !event.target.matches(MEDIA_SELECTOR)) {
      return;
    }

    lastNativeMedia = event.target;
    stopBackgroundMedia({ except: event.target, resetHiddenIframes: true });
  }, true);

  document.addEventListener('click', (event) => {
    const target = event.target;

    if (!target || isMediaControlClick(target)) {
      return;
    }

    if (target.closest && target.closest(INTERACTION_SELECTOR)) {
      const activeIframe = target.closest(EMBED_SELECTOR);
      const isStoryInteraction = !!target.closest(STORY_SELECTOR);

      stopBackgroundMedia({
        except: activeIframe,
        resetHiddenIframes: true,
        forceReset: isStoryInteraction
      });
      scheduleHiddenIframeCleanup();
    }
  }, true);

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopBackgroundMedia({ forceReset: true });
    }
  });

  window.addEventListener('pagehide', () => {
    stopBackgroundMedia({ forceReset: true });
  });

  document.addEventListener('hidden.bs.modal', () => {
    stopBackgroundMedia({ forceReset: true });
  });

  document.addEventListener('show.bs.modal', () => {
    stopBackgroundMedia({ resetHiddenIframes: true });
  });

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        rememberIframeSources(node);
      });

      mutation.removedNodes.forEach((node) => {
        if (!node || !node.querySelectorAll) {
          return;
        }

        node.querySelectorAll(MEDIA_SELECTOR).forEach((media) => {
          try {
            media.pause();
          } catch (error) {
            // Ignore removed media that is already torn down.
          }
        });

        node.querySelectorAll(EMBED_SELECTOR).forEach(pauseEmbeddedPlayer);
      });
    });
  });

  if (document.documentElement) {
    observer.observe(document.documentElement, {
      childList: true,
      subtree: true
    });
  }

  window.kosherStopBackgroundMedia = stopBackgroundMedia;
}());
