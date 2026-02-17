export const insightsPosts = [
  {
    slug: 'lagos-waterfront-demand',
    category: 'Market',
    title: 'Lagos Waterfront Demand Is Surging',
    excerpt: 'Banana Island and Lekki Phase 1 are setting new benchmarks for premium demand.',
    image: '/uploads/properties/1765547677_ibadan_apt_overview.jpg',
    tag: 'Market Pulse',
    body: [
      'Waterfront inventory in Lagos remains constrained while corporate demand is rising.',
      'Agents are closing faster when listings include verified legal docs, virtual tours, and concierge scheduling.',
      'Pricing momentum remains strongest in locations with premium security and transport access.'
    ]
  },
  {
    slug: 'abuja-executive-buyer',
    category: 'Strategy',
    title: 'Inside the Abuja Executive Buyer',
    excerpt: 'How discreet showings and executive lounges influence closing velocity.',
    image: '/uploads/properties/1762164599_Abuja_inner.png',
    tag: 'Field Notes',
    body: [
      'Executive buyers prioritize discretion, quick response, and clarity on title history.',
      'Preferred agencies now package private tours, airport pickup, and same-day paperwork review.',
      'Teams that shorten negotiation cycles are outperforming in Abuja premium segments.'
    ]
  },
  {
    slug: 'shortlet-yield-pressure',
    category: 'Yield',
    title: 'Shortlet Yields Are Reshaping the Stack',
    excerpt: 'Operators are prioritizing service design to protect premium nightly rates.',
    image: '/uploads/properties/1761861139_swimming_images.jpeg',
    tag: 'Yield Watch',
    body: [
      'Shortlet competition is increasing, but branded service standards still command higher occupancy.',
      'Operational discipline around cleaning turnaround and guest support is now a pricing lever.',
      'Owners with strong agency reporting are optimizing yield by season and location.'
    ]
  },
  {
    slug: 'hotel-playbooks',
    category: 'Hospitality',
    title: 'Hotel Playbooks for the New Luxury Class',
    excerpt: 'What the best Lagos hospitality teams are doing to retain VIP guests.',
    image: '/uploads/properties/1765548482_Las_vegas_apt_overview.webp',
    tag: 'Hospitality',
    body: [
      'Hospitality teams are combining lifestyle curation with stronger after-stay engagement.',
      'Cross-selling premium residences is working best when guest profiles are structured in CRM.',
      'Top operators are using data-led service recovery to protect brand loyalty.'
    ]
  },
  {
    slug: 'residential-amenities',
    category: 'Design',
    title: 'Residential Amenity Packages That Close',
    excerpt: 'Concierge tech, wellness floors, and curated art corridors win attention.',
    image: '/uploads/properties/1765545623_Las_vegas_inner.webp',
    tag: 'Design',
    body: [
      'Wellness and community features are now weighted similarly to bedroom counts in premium deals.',
      'Lifestyle positioning with clear visual storytelling improves qualified lead conversion.',
      'Developers pairing amenities with transparent service fees are seeing stronger trust.'
    ]
  },
  {
    slug: 'land-banking',
    category: 'Strategy',
    title: 'Land Banking in Secondary Cities',
    excerpt: 'Ibadan and Ilorin corridors are quietly outperforming earlier projections.',
    image: '/uploads/properties/1765547799_ilorin_apt_overview.jpg',
    tag: 'Strategy',
    body: [
      'Secondary city land corridors are attracting patient capital from portfolio buyers.',
      'Infrastructure visibility and regulatory clarity are key filters for due diligence.',
      'Agents that provide scenario-based holding timelines are winning investor confidence.'
    ]
  }
]

export function getInsightBySlug(slug) {
  return insightsPosts.find((item) => item.slug === slug) || null
}
