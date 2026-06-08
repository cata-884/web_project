/**
 * Definitii JSDoc partajate, oglindind formele DTO-urilor din backend/app/dto.
 * Fisierul nu contine cod executabil - tipurile sunt referite din celelalte
 * fisiere prin `import('./types.js').NumeTip`.
 */

/**
 * @typedef {{ id: number, type: string, url: string }} CampingMediaItem
 */

/**
 * @typedef {{ id: number, type: string }} ReviewMediaItem
 */

/**
 * @typedef {{ username: string|null, avatar_url: string|null }} ReviewAuthor
 */

/**
 * @typedef {{
 *   id: number, user_id: number, rating: number, content: string|null,
 *   media: ReviewMediaItem[], author: ReviewAuthor
 * }} ReviewRow
 */

/**
 * @typedef {{
 *   id: number, name: string, description: string|null, type: string,
 *   region: string|null, address: string|null, latitude: number, longitude: number,
 *   price_per_night: number|null, capacity: number|null, rating_avg: number|null,
 *   media: CampingMediaItem[]
 * }} CampingDetail
 */

/**
 * @typedef {{
 *   id: number, name: string, slug: string, type: string, region: string|null,
 *   latitude: number, longitude: number, price_per_night: number|null, capacity: number|null,
 *   rating_avg: number|null, rating_count: number, cover_url: string|null, created_at: string|null
 * }} CampingListRow
 */

/**
 * @typedef {{
 *   id: number, name: string, type: string, region: string|null, price_per_night: number|null,
 *   approval_status: number, admin_feedback: string|null, created_at: string|null,
 *   full_name: string|null, username: string, email: string,
 *   company_name: string|null, business_type: string|null, registration_number: string|null,
 *   address_street: string|null, address_number: string|null, address_city: string|null, address_zip: string|null,
 *   contact_phone: string|null, contact_email: string|null,
 *   id_document_path: string|null, registration_document_path: string|null
 * }} AdminCampingRow
 */

/**
 * @typedef {{
 *   id: number, username: string, role: string, is_banned: boolean|string,
 *   avatar_url: string|null, full_name: string|null, email: string, created_at: string|null
 * }} AdminUserRow
 */

/**
 * @typedef {{ natural?: string, amenity?: string, tourism?: string, leisure?: string, name?: string }} POITags
 */

/**
 * @typedef {{
 *   id: number, camping_name: string|null, check_in: string, check_out: string,
 *   guests: number, status: string,
 *   camping: { name: string|null, slug: string|null, region: string|null, cover_url: string|null }
 * }} BookingRow
 */

/**
 * @typedef {{ id: number, username: string, email: string, full_name: string|null, avatar_url: string|null }} UserProfile
 */

/**
 * @typedef {{
 *   id: number, name: string, slug: string, type: string,
 *   region: string|null, price_per_night: number|null
 * }} WishlistCamping
 */

/**
 * @typedef {{ id: number, name: string }} SectionRow
 */

/**
 * @typedef {{
 *   id: number, name: string, slug: string, type: string, lat: number, lng: number,
 *   price: number|null, rating: number|null, image_url: string|null
 * }} MapMarker
 */
