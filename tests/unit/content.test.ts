import { describe, expect, it } from 'vitest';
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

const species = JSON.parse(
  readFileSync(
    new URL(
      '../../wordpress/plugins/naturedex-core/data/species.json',
      import.meta.url,
    ),
    'utf8',
  ),
) as Array<{
  id: string;
  dexNumber: number;
  name: string;
  scientificName: string;
  group: string;
  sprite: string;
  regionIds: string[];
  regionNote: string;
  sourceURLs: string[];
  size: {
    lengthCm: [number, number] | null;
    weightKg: [number, number] | null;
    note: string;
  };
}>;

describe('the Virginia field-guide content contract', () => {
  it('provides distinct, source-backed entries spanning all six animal groups', () => {
    expect(species.length).toBeGreaterThanOrEqual(24);
    expect(new Set(species.map((entry) => entry.id)).size).toBe(species.length);
    expect(new Set(species.map((entry) => entry.dexNumber)).size).toBe(
      species.length,
    );
    expect(new Set(species.map((entry) => entry.group))).toEqual(
      new Set([
        'Mammals',
        'Birds',
        'Reptiles',
        'Amphibians',
        'Fish',
        'Invertebrates',
      ]),
    );
    for (const entry of species) {
      expect(entry.id).toMatch(/^[a-z0-9]+(?:-[a-z0-9]+)*$/);
      expect(entry.name.trim()).not.toBe('');
      expect(entry.scientificName.trim()).not.toBe('');
      expect(entry.sourceURLs.length).toBeGreaterThan(0);
      for (const source of entry.sourceURLs)
        expect(new URL(source).protocol).toBe('https:');
    }
  });

  it('ships its sprites locally and distinguishes regional guidance from observations', () => {
    const regions = new Set(['mountains', 'valley', 'piedmont', 'coastal']);
    for (const entry of species) {
      expect(entry.sprite).toMatch(/^[a-z0-9-]+$/);
      expect(
        existsSync(
          resolve(
            process.cwd(),
            `wordpress/plugins/naturedex-core/assets/sprites/${entry.sprite}.svg`,
          ),
        ),
      ).toBe(true);
      expect(entry.regionIds.length).toBeGreaterThan(0);
      for (const region of entry.regionIds)
        expect(regions.has(region)).toBe(true);
      expect(entry.regionNote.toLowerCase()).toContain('illustrative');
      expect(entry.regionNote.toLowerCase()).toContain('not');
    }
  });

  it('keeps available physical measurements ordered and positive', () => {
    for (const entry of species) {
      for (const range of [entry.size.lengthCm, entry.size.weightKg]) {
        if (range === null) continue;
        expect(range).toHaveLength(2);
        expect(range[0]).toBeGreaterThan(0);
        expect(range[1]).toBeGreaterThanOrEqual(range[0]);
      }
    }
  });

  it('places every region marker inside the Virginia outline', () => {
    const read = (path: string) =>
      JSON.parse(
        readFileSync(
          resolve(process.cwd(), `wordpress/plugins/naturedex-core/${path}`),
          'utf8',
        ),
      );
    const regions = read('data/regions.json') as Array<{
      id: string;
      x: number;
      y: number;
    }>;
    const polygons = read('assets/map/virginia.geojson').geometry
      .coordinates as number[][][][];
    // The same projection as naturedex_map_outline() in the theme.
    const outline = polygons
      .map(([ring]) =>
        ring.map(([lon, lat]) => [
          ((lon + 83.8) / 8.7) * 620 + 30,
          ((39.6 - lat) / 3.2) * 300 + 40,
        ]),
      )
      .sort((a, b) => b.length - a.length)[0];
    const inside = (x: number, y: number) =>
      outline.reduce((isInside, [x1, y1], index) => {
        const [x2, y2] = outline[(index + 1) % outline.length];
        const crosses =
          y1 > y !== y2 > y && x < ((x2 - x1) * (y - y1)) / (y2 - y1) + x1;
        return crosses ? !isInside : isInside;
      }, false);
    for (const region of regions)
      expect(inside(region.x, region.y), region.id).toBe(true);
  });
});
