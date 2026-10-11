import Image from "next/image";

const images: Record<string, string> = {
  Liturgy: "/images/chapel.webp",
  Recollection: "/images/reflection.webp",
  Retreat: "/images/retreat.webp",
  "Formation program": "/images/retreat.webp",
};

export function ActivityArtwork({
  type,
  detail = false,
  children,
}: {
  type: string;
  detail?: boolean;
  children?: React.ReactNode;
}) {
  return (
    <div className={detail ? "detail-art" : "activity-art"}>
      <Image
        className={type === "Liturgy" ? "chapel-art-image" : undefined}
        src={images[type] ?? "/images/chapel.webp"}
        alt=""
        fill
        sizes={detail ? "(max-width: 800px) 100vw, 200px" : "(max-width: 800px) 100vw, 350px"}
      />
      {detail ? <span className="artwork-label">{type}</span> : children}
      <small className="artwork-credit">Illustrative image</small>
    </div>
  );
}
