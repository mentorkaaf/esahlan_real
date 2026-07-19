export interface User {
  id: number;
  name: string;
  phone: string;
  email?: string;
  avatar?: string;
  role: string;
}

// Matches backend transformUser() output
export interface CommunityProfile {
  id: number;
  name: string;           // backend field (display name)
  username?: string;
  bio?: string;
  avatar?: string;
  cover_photo?: string;
  followers_count: number;
  following_count: number;
  posts_count: number;
  is_following: boolean;
  is_verified: boolean;
  is_business?: boolean;
  // legacy alias used in profile-endpoint responses
  display_name?: string;
  user_id?: number;
}

// Matches backend transformPost() media shape
export interface PostMedia {
  id: number;
  type: 'image' | 'video';
  url: string;
  thumbnail?: string;      // backend field
  thumbnail_url?: string;  // alias used in some endpoints
  hls_url?: string;
  mp4_direct_url?: string;
  width?: number;
  height?: number;
  duration?: number;
  transcoding_status?: string;
}

// Matches backend transformPost() output
export interface CommunityPost {
  id: number;
  content?: string;
  type: 'post' | 'reel' | 'image';
  user?: CommunityProfile;    // backend field name
  profile?: CommunityProfile; // alias used in some responses
  media: PostMedia[];
  likes_count: number;
  comments_count: number;
  shares_count: number;
  saves_count: number;
  views_count: number;
  is_liked?: boolean;
  is_saved?: boolean;
  user_reaction?: string;
  hashtags?: string[];
  created_at: string;
  is_ad?: boolean;
  ad?: CommunityAd;
  page_id?: number;
  page?: { id: number; name: string; avatar?: string };
}

export interface CommunityAd {
  id: number;
  title: string;
  description?: string;
  cta_text: string;
  cta_url?: string;
  media_url?: string;
  media_type: 'image' | 'video';
}

export interface Story {
  id: number;
  profile: CommunityProfile;
  media_url: string;
  media_type: 'image' | 'video' | 'text';
  duration: number;
  text_content?: string;
  bg_color?: string;
  viewed: boolean;
  created_at: string;
  expires_at: string;
}

export interface StoryGroup {
  profile: CommunityProfile;
  stories: Story[];
  has_unseen: boolean;
}

export interface Chat {
  id: number;
  other_user: CommunityProfile;
  last_message?: Message;
  unread_count: number;
  updated_at: string;
}

export interface Message {
  id: number;
  chat_id: number;
  sender_id: number;
  content?: string;
  media_url?: string;
  media_type?: string;
  created_at: string;
  read_at?: string;
}

export interface FeedResponse {
  data: CommunityPost[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
  };
}

export interface Notification {
  id: number;
  type: string;
  title: string;
  body: string;
  data?: Record<string, string>;
  read_at?: string;
  created_at: string;
}
