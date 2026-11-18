import { describe, expect, it } from 'vitest'
import { coverImageProps, screenshotThumbnailProps } from './steamImages'

describe('coverImageProps', () => {
  it('serves the 300px cover by default and offers the 600px one for dense screens', () => {
    const url = 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/620/library_600x900_2x.jpg'

    expect(coverImageProps(url)).toEqual({
      src: 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/620/library_600x900.jpg',
      srcSet: 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/620/library_600x900.jpg 300w, https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/620/library_600x900_2x.jpg 600w',
    })
  })

  it('leaves other images alone (header-image fallbacks)', () => {
    const url = 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/620/header.jpg'
    expect(coverImageProps(url)).toEqual({ src: url })
  })
})

describe('screenshotThumbnailProps', () => {
  it('uses the 600px screenshot for thumbnails, keeping the query string', () => {
    const url = 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/1/abc/ss_abc.1920x1080.jpg?t=1'
    const small = 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/1/abc/ss_abc.600x338.jpg?t=1'

    expect(screenshotThumbnailProps(url)).toEqual({ src: small, srcSet: `${small} 600w, ${url} 1920w` })
  })
})
