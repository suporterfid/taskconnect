/** Parse "200-299, 404" style input into inclusive [min, max] pairs. */
export function parseSuccessStatusRanges(
  raw: string,
): Array<[number, number]> | null {
  const trimmed = raw.trim()
  if (trimmed === '') {
    return null
  }

  const ranges: Array<[number, number]> = []
  for (const part of trimmed.split(',')) {
    const token = part.trim()
    if (token === '') {
      continue
    }
    const match = /^(\d{3})(?:\s*-\s*(\d{3}))?$/.exec(token)
    if (!match) {
      throw new Error(`Invalid status range: ${token}`)
    }
    const min = Number(match[1])
    const max = Number(match[2] ?? match[1])
    if (min > max || min < 100 || max > 599) {
      throw new Error(`Invalid status range: ${token}`)
    }
    ranges.push([min, max])
  }

  return ranges.length > 0 ? ranges : null
}

export function formatSuccessStatusRanges(
  ranges?: Array<[number, number]> | number[][] | null,
): string {
  if (!ranges?.length) {
    return ''
  }
  return ranges
    .map((pair) => {
      const min = Number(pair[0])
      const max = Number(pair[1] ?? pair[0])
      return min === max ? String(min) : `${min}-${max}`
    })
    .join(', ')
}
