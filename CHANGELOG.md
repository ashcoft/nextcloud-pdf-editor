# [1.1.0](https://github.com/ashcoft/nextcloud-pdf-editor/compare/v1.0.0...v1.1.0) (2026-11-18)


### Bug Fixes

* **ci:** enable corepack with sudo so the CircleCI Node jobs can install pnpm ([3d718c6](https://github.com/ashcoft/nextcloud-pdf-editor/commit/3d718c62089d434d38e4e548396940b5766a91b9))
* **ci:** restore the live PdfCraft bundle when a fetch is interrupted ([64e1d16](https://github.com/ashcoft/nextcloud-pdf-editor/commit/64e1d16a0523d1ea9e63cc88e7c3a15b4d4c6c4b))
* **ci:** stop the Renovate and PdfCraft updaters failing on a missing secret ([1704c13](https://github.com/ashcoft/nextcloud-pdf-editor/commit/1704c130fff3889f4ab0c382a06cc5f6c384e932))
* **deps:** update PdfCraft to 0.21.1 ([1861340](https://github.com/ashcoft/nextcloud-pdf-editor/commit/1861340441783045b31e604c72dc2acd735eba2b))

# 1.0.0 (2026-11-10)


### Bug Fixes

* address review findings on the PDF editor app ([9e622d5](https://github.com/ashcoft/nextcloud-pdf-editor/commit/9e622d5b76b7128c114113710917abab42086165)), closes [PdfCraft#asset](https://github.com/PdfCraft/issues/asset)
* address the second round of review findings ([ebf76f4](https://github.com/ashcoft/nextcloud-pdf-editor/commit/ebf76f483e456a1e1d4e87adb6ae6b4ca065faa9))
* keep the fetch cleanup whole and keep unscanned PDFs listed ([c272e43](https://github.com/ashcoft/nextcloud-pdf-editor/commit/c272e43a1e510bf86159aff39cf5f60a1759b7e1))


### Features

* add a PDF editor app built on PdfCraft ([8948f33](https://github.com/ashcoft/nextcloud-pdf-editor/commit/8948f337b1e4c052a7a3da25278d61c9f3721eb7))
