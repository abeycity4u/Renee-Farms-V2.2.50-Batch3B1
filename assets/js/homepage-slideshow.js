/**
 * Public homepage slideshow behavior.
 * Externalized for CSP compatibility.
 */
(function () {
            const currentImage = document.getElementById('hero-slideshow-image-current');
            const nextImage = document.getElementById('hero-slideshow-image-next');
            if (!currentImage || !nextImage) return;

            const slides = [
                { src: 'assets/images/chick.png?v=2024.06.01', alt: 'Chick standing in farm grass' },
                { src: 'assets/images/bookkeeping.jpg?v=2024.06.01', alt: 'Farm bookkeeping and financial records' },
                { src: 'assets/images/eggs.jpg?v=2024.06.01', alt: 'Fresh farm eggs in a basket' },
                { src: 'assets/images/cow.jpg?v=2024.06.01', alt: 'Cow in a green pasture' },
                { src: 'assets/images/goat.jpg?v=2024.06.01', alt: 'Goat in a farm field' },
                { src: 'assets/images/sheeps.jpg?v=2024.06.01', alt: 'Sheep grazing on grassland' }
            ];

            const loadedSlides = new Set([slides[0].src]);
            let activeSlideIndex = 0;
            let isTransitioning = false;

            function preloadSlide(slide) {
                if (loadedSlides.has(slide.src)) return Promise.resolve(slide);

                return new Promise(function (resolve, reject) {
                    const image = new Image();
                    image.onload = function () {
                        loadedSlides.add(slide.src);
                        resolve(slide);
                    };
                    image.onerror = reject;
                    image.decoding = 'async';
                    image.src = slide.src;
                });
            }

            function preloadUpcomingSlides() {
                slides.slice(1).forEach(function (slide) {
                    preloadSlide(slide).catch(function () {});
                });
            }

            function showSlide(slideIndex) {
                if (isTransitioning || document.hidden) return;

                const slide = slides[slideIndex];
                isTransitioning = true;

                preloadSlide(slide)
                    .then(function () {
                        nextImage.src = slide.src;
                        nextImage.alt = slide.alt;
                        nextImage.removeAttribute('aria-hidden');
                        nextImage.classList.add('is-next', 'is-active');

                        window.setTimeout(function () {
                            currentImage.src = slide.src;
                            currentImage.alt = slide.alt;
                            nextImage.classList.remove('is-next', 'is-active');
                            nextImage.setAttribute('aria-hidden', 'true');
                            activeSlideIndex = slideIndex;
                            isTransitioning = false;
                        }, 760);
                    })
                    .catch(function () {
                        isTransitioning = false;
                    });
            }

            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(preloadUpcomingSlides, { timeout: 1800 });
            } else {
                window.setTimeout(preloadUpcomingSlides, 600);
            }

            window.setInterval(function () {
                const nextSlideIndex = (activeSlideIndex + 1) % slides.length;
                showSlide(nextSlideIndex);
            }, 3600);
        })();
